<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAcademicImports;
use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Jobs\ImportSubjectRowsJob;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectImportController extends Controller
{
    use HandlesAcademicImports, ScopesToProgram;

    public function preview(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        return response()->json($this->academicUpload($request, $program, 'subject'));
    }

    public function store(Request $request, Department $department, Program $program): JsonResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        return response()->json($this->academicImport($request, $program, 'subject', ['code', 'description', 'year'], [],
            fn ($rows, $mapping, $batch) => ImportSubjectRowsJob::dispatchSync($rows, $mapping, $batch->id, $program->id)));
    }
}
