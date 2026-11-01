<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ImportBatch;
use App\Models\Program;
use App\Support\ExcelReader;
use App\Rules\ImportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HandlesAcademicImports
{
    protected function academicUpload(Request $request, Program $program, string $type, ?int $periodId = null): array
    {
        $file = $request->validate(['file' => ['bail', 'required', 'file', 'max:5120', new ImportFile(['csv', 'xlsx', 'xls'])]])['file'];
        $path = $file->store('imports');
        try {
            $format = \PhpOffice\PhpSpreadsheet\IOFactory::identify(Storage::path($path));
            if (strtolower($file->getClientOriginalExtension()) !== strtolower($format)) {
                throw new \RuntimeException('File contents do not match its extension');
            }
            $rows = ExcelReader::toArray(Storage::path($path));
            if (count($rows) < 1 || count($rows) > 2000 || ! collect($rows)->flatten()->contains(fn ($cell) => trim((string) $cell) !== '')) {
                throw new \RuntimeException('Invalid row count');
            }
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw ValidationException::withMessages(['file' => 'Use a readable CSV, XLSX or XLS file with 1–2,000 rows including its header.']);
        }

        $token = (string) Str::uuid();
        Cache::put('academic-upload:'.$token, [
            'path' => $path, 'owner' => $request->user()->id, 'program' => $program->id,
            'type' => $type, 'period' => $periodId,
        ], now()->addMinutes(30));

        return ['token' => $token, 'totalRows' => count($rows), 'previewRows' => array_slice($rows, 0, 15)];
    }

    protected function academicImport(Request $request, Program $program, string $type, array $required, array $optional, callable $run, ?int $periodId = null, bool $review = false): array
    {
        $rules = [
            'token' => ['required', 'uuid'],
            'mapping' => ['required', 'array:'.implode(',', [...$required, ...$optional])],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:255', 'distinct'],
            'startRow' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
        foreach ($required as $field) {
            $rules['mapping.'.$field] = ['required', 'integer', 'min:0', 'max:255'];
        }
        $data = $request->validate($rules);
        $columns = array_filter($data['mapping'], fn ($value) => $value !== null);
        if (count(array_unique($columns)) !== count($columns)) {
            throw ValidationException::withMessages(['mapping' => 'Map each field to a different column.']);
        }
        $lock = Cache::lock('academic-import:'.$data['token'], 120);
        abort_unless($lock->get(), 409, 'This upload is already being imported.');
        try {
            $upload = Cache::get('academic-upload:'.$data['token']);
            abort_unless($upload, 422, 'Upload expired or already imported. Please upload again.');
            abort_unless($upload['owner'] === $request->user()->id && $upload['program'] === $program->id && $upload['type'] === $type && $upload['period'] === $periodId, 403, 'This upload belongs to a different admin, program, import type or term.');
            try {
                $rows = ExcelReader::toArray(Storage::path($upload['path']));
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['file' => 'The upload cannot be read. Please upload again.']);
            }
            $width = max(array_map('count', $rows));
            foreach ($data['mapping'] as $column) {
                if ($column !== null && $column >= $width) {
                    throw ValidationException::withMessages(['mapping' => 'A mapped column is outside the uploaded file.']);
                }
            }
            $rows = array_slice($rows, $data['startRow'] - 1, null, true);
            abort_unless(count($rows), 422, 'No data rows remain at the selected start row.');
            if ($review) return $run($rows,$data['mapping'],null);
            $batch = ImportBatch::create([
                'type' => $type, 'program_id' => $program->id, 'department_id' => $program->department_id,
                'created_by' => $request->user()->id, 'total_rows' => count($rows),
            ]);
            // A single synchronous job retains whole-file conflict detection and the existing batch/poll UI.
            $run($rows, $data['mapping'], $batch);
            $batch->markCompleted();
            Cache::forget('academic-upload:'.$data['token']);
            Storage::delete($upload['path']);

            return ['batchId' => $batch->id];
        } finally {
            $lock->release();
        }
    }
}
