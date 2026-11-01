<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAcademicImports;
use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Jobs\ImportClassRowsJob;
use App\Support\ActiveClassPeriod;
use App\Models\{Department, Program};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassImportController extends Controller
{
    use HandlesAcademicImports, ScopesToProgram;

    public function preview(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        $data = $request->validate(['termId' => ['required', 'integer', 'exists:review_periods,id']]);

        $periodId = ActiveClassPeriod::resolve((int) $data['termId']);
        return response()->json($this->academicUpload($request, $program, 'class', $periodId));
    }

    public function store(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        $data = $request->validate(['termId' => ['required', 'integer', 'exists:review_periods,id']]);
        $periodId = ActiveClassPeriod::resolve((int) $data['termId']);

        return response()->json($this->academicImport($request, $program, 'class', ['year', 'block', 'subjectCode'], ['facultyId', 'description', 'facultyName'],
            fn ($rows, $mapping, $batch) => ImportClassRowsJob::dispatchSync($rows, $mapping, $batch->id, $program->id, $periodId), $periodId));
    }
}
