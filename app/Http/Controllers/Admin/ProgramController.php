<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    use ScopesToProgram;

    // "Manage Department" page — its list of programs
    public function show(Department $department): Response
    {
        return Inertia::render('Admin/Departments/Partials/ManageDepartmentView', [
            'department' => $department,
            'programs' => $department->programs()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:programs,code,NULL,id,department_id,'.$department->id],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->programs()->create($data);
        Cache::flush();

        return back()->with('status', 'Program added.');
    }

    public function update(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:programs,code,'.$program->id.',id,department_id,'.$department->id],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $program->update($data);
        Cache::flush();

        return back()->with('status', 'Program updated.');
    }

    public function destroy(Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $program->delete();
        Cache::flush();

        return back()->with('status', 'Program deleted.');
    }
}
