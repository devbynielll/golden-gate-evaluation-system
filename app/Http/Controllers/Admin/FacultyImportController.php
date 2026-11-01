<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesExcelImports;
use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Jobs\ImportFacultyRowsJob;
use App\Models\Department;
use App\Models\ImportBatch;
use App\Models\Program;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FacultyImportController extends Controller
{
    use HandlesExcelImports, ScopesToProgram;

    public function preview(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        return response()->json($this->storeUpload($request));
    }

    public function store(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'startRow' => ['required', 'integer', 'min:1'],
        ]);

        $rows = $this->resolveUploadedRows($data['token']);
        $dataRows = array_slice($rows, $data['startRow'] - 1, null, true);

        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_FACULTY,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'total_rows' => count($dataRows),
            'created_by' => $request->user()->id,
        ]);

        $jobs = array_map(
            fn (array $chunk) => new ImportFacultyRowsJob(
                $chunk, $data['mapping'], array_key_first($chunk) + 1, $batch->id, $program->id,
            ),
            $this->chunkRows($dataRows),
        );

        $path = Cache::pull("import-upload:{$data['token']}")['path'] ?? null;
        Bus::batch($jobs)
            ->onQueue('imports')
            ->then(function (Batch $busBatch) use ($batch, $path) {
                $batch->markCompleted();
                if ($path) {
                    Storage::delete($path);
                }
            })
            ->dispatch();

        return response()->json(['batchId' => $batch->id]);
    }
}
