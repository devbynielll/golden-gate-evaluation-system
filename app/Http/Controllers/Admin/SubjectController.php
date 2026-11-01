<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use ScopesToProgram;

    public function store(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $this->validated($request, $program);
        $program->subjects()->create($data);

        return back()->with('status', 'Subject added.');
    }

    public function update(Request $request, Department $department, Program $program, Subject $subject): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($subject->program_id === $program->id, 404);

        $subject->update($this->validated($request, $program, $subject));

        return back()->with('status', 'Subject updated.');
    }

    public function destroy(Department $department, Program $program, Subject $subject): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($subject->program_id === $program->id, 404);

        $subject->delete();

        return back()->with('status', 'Subject deleted.');
    }

    private function validated(Request $request, Program $program, ?Subject $subject = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                'unique:subjects,code,'.($subject?->id ?? 'NULL').',id,program_id,'.$program->id,
            ],
            'description' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1', 'max:6'],
            'credits' => ['nullable', 'integer', 'min:0', 'max:12'],
        ], [], ['year' => 'year level']);

        $data = [
            'code' => $validated['code'],
            'description' => $validated['description'],
            'year_level' => $validated['year'],
        ];

        // Older clients may still send credits; omission must preserve stored values.
        if (array_key_exists('credits', $validated)) {
            $data['credits'] = $validated['credits'];
        }

        return $data;
    }
}
