<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class FacultyController extends Controller
{
    use ScopesToProgram;

    public function store(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $this->validated($request, $program);
        $faculty = $program->faculty()->create([
            'faculty_id' => $data['facultyId'],
            'name' => $data['name'],
            'photo_path' => $this->storePhoto($request),
        ]);
        if (array_key_exists('subjectIds', $data)) {
            $faculty->qualifiedSubjects()->sync($data['subjectIds']);
        }

        return back()->with('status', 'Faculty added.');
    }

    public function update(Request $request, Department $department, Program $program, Faculty $faculty): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($faculty->program_id === $program->id, 404);

        $data = $this->validated($request, $program, $faculty);

        $photoPath = $faculty->photo_path;
        if ($request->hasFile('photo')) {
            $this->deletePhoto($photoPath);
            $photoPath = $this->storePhoto($request);
        }

        $faculty->update([
            'faculty_id' => $data['facultyId'],
            'name' => $data['name'],
            'photo_path' => $photoPath,
        ]);
        if (array_key_exists('subjectIds', $data)) {
            $faculty->qualifiedSubjects()->sync($data['subjectIds']);
        }

        return back()->with('status', 'Faculty updated.');
    }

    public function destroy(Department $department, Program $program, Faculty $faculty): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($faculty->program_id === $program->id, 404);

        DB::transaction(function () use ($faculty) {
            // Match submission/assignment lock ordering before checking all periods.
            \App\Models\ReviewPeriod::orderBy('id')->lockForUpdate()->get();
            $record = Faculty::whereKey($faculty->id)->lockForUpdate()->firstOrFail();
            if ($record->classSubjects()->whereHas('evaluations')->exists()) {
                throw ValidationException::withMessages(['faculty' => 'This faculty member has evaluation history and cannot be deleted. Preserve the faculty record.']);
            }
            // Perform no destructive file operation before history validation succeeds.
            $this->deletePhoto($record->photo_path);
            $record->delete();
        }, 3);

        return back()->with('status', 'Faculty removed.');
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('faculty-photos', 'public')
            : null;
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function validated(Request $request, Program $program, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'facultyId' => [
                'required', 'string', 'max:20',
                'unique:faculty,faculty_id,'.($faculty?->id ?? 'NULL'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subjectIds' => ['array'],
            'subjectIds.*' => [Rule::exists('subjects', 'id')->where('program_id', $program->id)],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}
