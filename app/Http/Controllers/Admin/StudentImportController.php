<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesExcelImports;
use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Jobs\ImportStudentRowsJob;
use App\Models\Department;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Services\StudentImportAnalyzer;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StudentImportController extends Controller
{
    use HandlesExcelImports, ScopesToProgram;

    private const PREVIEW_LIMIT = 500;

    public function preview(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $mode = $request->validate(['mode' => ['sometimes', 'in:regular,irregular,mixed']])['mode'] ?? null;
        $upload = $this->storeUpload($request);
        if ($mode !== null) {
            Cache::put('student-roster-upload:'.$upload['token'], [
                'owner' => $request->user()->id, 'program' => $program->id, 'mode' => $mode,
            ], now()->addMinutes(30));
        }

        return response()->json($upload);
    }

    // dry run, writes nothing
    public function validateImport(Request $request, Department $department, Program $program, StudentImportAnalyzer $analyzer): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        $data = $this->validatedImport($request);

        $results = $analyzer->analyze(
            $program,
            $analyzer->group($this->dataRows($request, $program, $data), $data['mapping']),
            $data['updateExisting'],
            $data['regularOnly'],
            $data['irregularOnly'],
            $data['mixed'],
        );
        $count = fn (string $action) => count(array_filter($results, fn ($r) => $r['action'] === $action));

        return response()->json([
            'summary' => [
                'students' => count($results),
                'create' => $count(StudentImportAnalyzer::ACTION_CREATE),
                'enroll' => $count(StudentImportAnalyzer::ACTION_ENROLL),
                'skip' => $count(StudentImportAnalyzer::ACTION_SKIP),
                'error' => $count(StudentImportAnalyzer::ACTION_ERROR),
                ...($data['rosterOnly'] ? [
                    'regular' => count(array_filter($results, fn ($r) => $r['action'] === StudentImportAnalyzer::ACTION_CREATE && ! $r['isIrregular'])),
                    'irregular' => count(array_filter($results, fn ($r) => $r['action'] === StudentImportAnalyzer::ACTION_CREATE && $r['isIrregular'])),
                ] : []),
                'subjectsToAdd' => array_sum(array_map(fn ($r) => count($r['subjectIds']), $results)),
            ],
            // errors and skips first
            'rows' => collect($results)
                ->sortBy(fn ($r) => match ($r['action']) {
                    StudentImportAnalyzer::ACTION_ERROR => 0,
                    StudentImportAnalyzer::ACTION_SKIP => 1,
                    default => 2,
                })
                ->take(self::PREVIEW_LIMIT)
                ->map(fn ($r) => [
                    'rows' => $r['rows'],
                    'studentId' => $r['fields']['studentId'],
                    'name' => trim("{$r['fields']['firstName']} {$r['fields']['lastName']}"),
                    'action' => $r['action'],
                    'studentType' => in_array(strtolower($r['fields']['isIrregular']), ['1', 'y', 'yes', 'true'], true) ? 'Irregular' : 'Regular',
                    'subjects' => $r['subjectCodes'],
                    'messages' => $r['messages'],
                    'warnings' => $r['warnings'],
                ])->values(),
            'truncated' => count($results) > self::PREVIEW_LIMIT,
            // enrollments go to the active term
            ...($data['rosterOnly'] ? [] : ['term' => ReviewPeriod::where('is_active', true)->first()?->label()]),
        ]);
    }

    public function store(Request $request, Department $department, Program $program, StudentImportAnalyzer $analyzer): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        $data = $this->validatedImport($request);

        $records = $analyzer->group($this->dataRows($request, $program, $data), $data['mapping']);

        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_STUDENT,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'total_rows' => count($records),
            'created_by' => $request->user()->id,
        ]);

        $jobs = array_map(
            fn (array $chunk) => new ImportStudentRowsJob($chunk, $batch->id, $program->id, $data['updateExisting'], $data['regularOnly'], $data['irregularOnly'], $data['mixed']),
            array_chunk($records, 5),
        );

        $path = Cache::pull("import-upload:{$data['token']}")['path'] ?? null;
        $busBatch = Bus::batch($jobs)
            ->onQueue('imports')
            ->then(static function (Batch $busBatch) use ($batch, $path) {
                $batch->markCompleted();
                if ($path) {
                    Storage::delete($path);
                }
            })
            ->catch(static function (Batch $busBatch, \Throwable $error) use ($batch) {
                $batch->markFailed();
            })
            ->dispatch();
        $batch->update(['bus_batch_id' => $busBatch->id]);
        if ($jobs === []) {
            $batch->markCompleted();
        }

        return response()->json(['batchId' => $batch->id]);
    }

    private function validatedImport(Request $request): array
    {
        $regularOnly = $request->input('mode') === 'regular';
        $irregularOnly = $request->input('mode') === 'irregular';
        $mixed = $request->input('mode') === 'mixed';
        $rosterOnly = $regularOnly || $irregularOnly || $mixed;
        return $request->validate([
            'mode' => ['sometimes', 'in:regular,irregular,mixed'],
            'token' => ['required', 'string'],
            'mapping' => ['required', $rosterOnly ? 'array:studentId,email,firstName,middleName,lastName,suffix,year,block,isIrregular' : 'array'],
            'mapping.studentId' => ['required', 'integer', 'min:0'],
            'mapping.isIrregular' => [($irregularOnly || $mixed) ? 'required' : 'nullable', 'integer', 'min:0'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
            'startRow' => ['required', 'integer', 'min:1'],
            'updateExisting' => $rosterOnly ? ['prohibited'] : ['sometimes', 'boolean'],
        ]) + ['updateExisting' => false, 'regularOnly' => $regularOnly, 'irregularOnly' => $irregularOnly, 'rosterOnly' => $rosterOnly, 'mixed' => $mixed];
    }

    private function dataRows(Request $request, Program $program, array $data): array
    {
        $scope = Cache::get('student-roster-upload:'.$data['token']);
        if ($data['rosterOnly'] || $scope) {
            abort_unless($data['rosterOnly'] && $scope, 422, 'Please re-upload this file using the simplified Student Import.');
            abort_unless($scope['owner'] === $request->user()->id && $scope['program'] === $program->id, 403, 'This student upload belongs to another administrator or program.');
            abort_unless(($scope['mode'] ?? null) === $data['mode'], 422, 'Please re-upload this file using the same Student Import mode.');
        }

        return array_slice($this->resolveUploadedRows($data['token']), $data['startRow'] - 1, null, true);
    }
}
