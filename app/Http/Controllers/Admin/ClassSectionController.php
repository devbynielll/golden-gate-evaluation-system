<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Program;
use App\Support\ClassScheduleVersion;
use App\Support\ActiveClassPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassSectionController extends Controller
{
    use ScopesToProgram;

    public function store(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        $data = $this->validated($request, $program);

        DB::transaction(function () use ($program, $data) {
            ActiveClassPeriod::lock($data['termId']);
            $section = $program->classSections()->create([
                'year_level' => $data['year'], 'block' => $data['block'],
            ]);
            $this->syncSubjects($section, $data['termId'], $data['assignments']);
        });
        Cache::flush();

        return back()->with('status', 'Class section added.');
    }

    public function update(Request $request, Department $department, Program $program, ClassSection $classSection): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($classSection->program_id === $program->id, 404);
        $data = $this->validated($request, $program, $classSection);

        DB::transaction(function () use ($classSection, $data) {
            ActiveClassPeriod::lock($data['termId']);
            // Evaluation submission takes the same section lock before its offering lock.
            $section = ClassSection::whereKey($classSection->id)->lockForUpdate()->firstOrFail();
            $changesIdentity = $section->year_level !== (int) $data['year'] || $section->block !== $data['block'];
            if ($changesIdentity && $section->classSubjects()->whereHas('evaluations')->exists()) {
                throw ValidationException::withMessages(['block' => 'This class has evaluations, so its year or block cannot be changed.']);
            }

            if ($changesIdentity && $this->hasHistoricalSchedule($section, $data['termId'])) {
                throw ValidationException::withMessages(['block' => 'This class has historical schedules, so its year or block cannot be changed.']);
            }

            $this->syncSubjects($section, $data['termId'], $data['assignments'], $data['scheduleVersion'] ?? null);
            $section->update(['year_level' => $data['year'], 'block' => $data['block']]);
        });
        Cache::flush();

        return back()->with('status', 'Class section updated.');
    }

    public function destroy(Request $request, Department $department, Program $program, ClassSection $classSection): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($classSection->program_id === $program->id, 404);

        $context = $request->validate(['termId' => ['nullable', 'integer', 'exists:review_periods,id']]);
        $termId = ActiveClassPeriod::resolve($context['termId'] ?? null);
        DB::transaction(function () use ($classSection, $termId) {
            ActiveClassPeriod::lock($termId);
            $section = ClassSection::whereKey($classSection->id)->lockForUpdate()->firstOrFail();
            if ($section->classSubjects()->whereHas('evaluations')->exists()) {
                throw ValidationException::withMessages(['subjectIds' => 'This class has evaluations and cannot be deleted.']);
            }
            if ($this->hasHistoricalSchedule($section, $termId)) {
                throw ValidationException::withMessages(['subjectIds' => 'This class has historical schedules and cannot be deleted.']);
            }
            $section->delete();
        });
        Cache::flush();

        return back()->with('status', 'Class section deleted.');
    }

    private function hasHistoricalSchedule(ClassSection $section, int $activeId): bool
    {
        return $section->classSubjects()->where(fn ($query) => $query
            ->whereNull('review_period_id')->orWhere('review_period_id', '!=', $activeId))->exists();
    }

    private function syncSubjects(ClassSection $section, int $termId, array $assignments, ?string $version = null): void
    {
        $existing = $section->classSubjects()->where('review_period_id', $termId)
            ->orderBy('id')->lockForUpdate()->with('subject:id,code')->get();
        if ($version !== null && ! hash_equals(ClassScheduleVersion::for($section, $termId, $existing), $version)) {
            throw ValidationException::withMessages(['scheduleVersion' => 'This schedule changed while you were editing. Reload the page and review the current assignments.']);
        }

        $submitted = Evaluation::whereIn('class_subject_id', $existing->pluck('id'))
            ->lockForUpdate()->pluck('class_subject_id')->flip();
        $removed = $existing->filter(fn ($cs) => ! array_key_exists($cs->subject_id, $assignments));
        $reassigned = $existing->filter(fn ($cs) => array_key_exists($cs->subject_id, $assignments)
            && $cs->faculty_id !== $assignments[$cs->subject_id]);
        $blocked = $removed->merge($reassigned)->filter(fn ($cs) => $submitted->has($cs->id));
        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'subjectIds' => 'Evaluations were already submitted for '.$blocked->pluck('subject.code')->unique()->implode(', ')
                    .' this term, so its subject or instructor cannot be changed.',
            ]);
        }

        $section->classSubjects()->whereIn('id', $removed->pluck('id'))->delete();
        foreach ($reassigned as $offering) {
            $offering->update(['faculty_id' => $assignments[$offering->subject_id]]);
        }
        foreach (array_diff(array_keys($assignments), $existing->pluck('subject_id')->all()) as $subjectId) {
            $section->classSubjects()->create([
                'subject_id' => $subjectId, 'faculty_id' => $assignments[$subjectId], 'review_period_id' => $termId,
            ]);
        }
    }

    private function validated(Request $request, Program $program, ?ClassSection $section = null): array
    {
        $perSubject = $request->exists('assignments');
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1', 'max:6'],
            'block' => ['required', 'string', 'max:10', Rule::unique('class_sections', 'block')
                ->where('program_id', $program->id)->where('year_level', $request->input('year'))->ignore($section?->id)],
            'termId' => ['nullable', 'integer', 'exists:review_periods,id'],
            'scheduleVersion' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*' => ['array:subjectId,instructorId'],
            'assignments.*.subjectId' => ['required', 'integer', 'distinct', Rule::exists('subjects', 'id')->where('program_id', $program->id)],
            // Faculty IDs are global to this institution. Subjects and sections remain scoped
            // to the selected program, but an existing faculty member may teach across programs.
            'assignments.*.instructorId' => ['present', 'nullable', 'integer', Rule::exists('faculty', 'id')],
            // Preserve old clients' uniform-assignment requests, without ambiguous mixed payloads.
            'subjectIds' => [Rule::prohibitedIf($perSubject), 'array'],
            'subjectIds.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')->where('program_id', $program->id)],
            'instructorId' => [Rule::prohibitedIf($perSubject), 'nullable', 'integer', Rule::exists('faculty', 'id')],
        ], [], ['year' => 'year level']);

        $validated['termId'] = ActiveClassPeriod::resolve($validated['termId'] ?? null);

        $assignments = $perSubject
            ? $validated['assignments']
            : array_map(fn ($id) => ['subjectId' => $id, 'instructorId' => $validated['instructorId'] ?? null], $validated['subjectIds'] ?? []);
        $validated['assignments'] = collect($assignments)->mapWithKeys(fn ($assignment) => [
            (int) $assignment['subjectId'] => $assignment['instructorId'] === null ? null : (int) $assignment['instructorId'],
        ])->all();

        return $validated;
    }
}
