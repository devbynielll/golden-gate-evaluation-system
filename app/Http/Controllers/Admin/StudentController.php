<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Mail\StudentAccountCreated;
use App\Models\Department;
use App\Models\ClassSubject;
use App\Models\Evaluation;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentSectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    use ScopesToProgram;

    public function store(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $this->validated($request);
        $classSectionId = $this->resolveClassSection($program, $data);
        $enrollments = $data['is_irregular'] ? $this->resolveEnrollments($request) : [];
        unset($data['year'], $data['block']);

        $password = Str::password(12);

        // stop a double-click from firing two registrations
        $lock = Cache::lock('register-student:'.$data['email'], 15);

        if (! $lock->get()) {
            return back()->with('status', 'That registration is already in progress. Please wait a moment.');
        }

        try {
            DB::transaction(function () use ($program, $data, $classSectionId, $enrollments, $password) {
                $user = User::create([
                    'name' => trim("{$data['first_name']} {$data['last_name']}"),
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'role' => User::ROLE_STUDENT,
                    'id_number' => $data['student_number'],
                ]);
                // admin already vetted this account, no need to verify by email
                $user->markEmailAsVerified();

                $student = $program->students()->create([
                    ...$data,
                    'class_section_id' => $classSectionId,
                    'user_id' => $user->id,
                ]);

                if ($data['is_irregular']) {
                    $student->enrolledSubjects()->sync($enrollments);
                }

                // if this fails, the whole registration rolls back — no account without a delivered password
                Mail::to($student->email)->send(new StudentAccountCreated($student, $password));
            });
        } catch (\Throwable $e) {
            Log::error('Student registration failed: could not email login details.', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return back()->with('status', "Couldn't email the login details, so the student wasn't registered. Please try again.");
        } finally {
            $lock->release();
        }

        return back()->with('status', 'Student registered and login details emailed.');
    }

    public function update(Request $request, Department $department, Program $program, Student $student): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($student->program_id === $program->id, 404);

        $data = $this->validated($request, $student);
        DB::transaction(function () use ($request, $program, $student, $data) {
            // Same period/student lock order as evaluation submission. Re-resolve
            // assignments after locking so concurrent requests cannot orphan answers.
            ReviewPeriod::orderBy('id')->sharedLock()->get();
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $student->refresh();
            $classSectionId = $this->resolveClassSection($program, $data);
            $enrollments = $data['is_irregular'] ? $this->resolveEnrollments($request) : [];
            $periodId = ReviewPeriod::activeId();
            $offered = ClassSubject::where('review_period_id', $periodId ?? 0);
            if ($data['is_irregular']) {
                $offered->where(function ($q) use ($enrollments) {
                    foreach ($enrollments as $subjectId => $pivot) {
                        $q->orWhere(fn ($match) => $match->where('subject_id', $subjectId)->where('class_section_id', $pivot['class_section_id']));
                    }
                });
            } else {
                $offered->where('class_section_id', $classSectionId);
            }
            if (Evaluation::where('student_id', $student->id)->where('review_period_id', $periodId ?? 0)
                ->where('evaluatee_type', 'faculty')->whereNotNull('class_subject_id')->whereNotIn('class_subject_id', $offered->pluck('id'))->exists()) {
                throw ValidationException::withMessages(['subjectIds' => 'Subjects or class sections with existing evaluations cannot be removed or changed for this period.']);
            }
            unset($data['year'], $data['block']);
            $student->update([...$data, 'class_section_id' => $classSectionId]);

            // clears leftovers if switched back to regular
            $student->enrolledSubjects()->sync($data['is_irregular'] ? $enrollments : []);
        }, 3);

        Cache::flush();

        return back()->with('status', 'Student updated.');
    }

    public function destroy(Department $department, Program $program, Student $student): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);
        abort_unless($student->program_id === $program->id, 404);

        // removing the roster entry also revokes their login
        DB::transaction(function () use ($student) {
            $user = $student->user;
            $student->delete();
            $user?->delete();
        });

        return back()->with('status', 'Student removed.');
    }

    // year+block on the form, not by id — irregulars get no section
    private function resolveClassSection(Program $program, array $data): ?int
    {
        if ($data['is_irregular'] || ! $data['year'] || ! $data['block']) {
            return null;
        }

        return $program->classSections()
            ->firstOrCreate(['year_level' => $data['year'], 'block' => $data['block']])
            ->id;
    }

    // subject + block picks from the course selector
    // returns the sync() map: subject id => block
    private function resolveEnrollments(Request $request): array
    {
        $validated = $request->validate([
            'enrollments' => ['required_without:subjectIds', 'array', 'min:1'],
            'enrollments.*.subjectId' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'enrollments.*.classSectionId' => ['nullable', 'integer', 'exists:class_sections,id'],
            // bare ids still accepted
            'subjectIds' => ['required_without:enrollments', 'array', 'min:1'],
            'subjectIds.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ], [
            'enrollments.required_without' => 'Select at least one course for an irregular student.',
            'subjectIds.required_without' => 'Select at least one course for an irregular student.',
            'enrollments.min' => 'Select at least one course for an irregular student.',
            'subjectIds.min' => 'Select at least one course for an irregular student.',
        ]);

        $requested = isset($validated['enrollments'])
            ? collect($validated['enrollments'])->mapWithKeys(fn ($e) => [$e['subjectId'] => $e['classSectionId'] ?? null])
            : collect($validated['subjectIds'])->mapWithKeys(fn ($id) => [$id => null]);

        throw_unless(ReviewPeriod::activeId(), ValidationException::withMessages([
            'subjectIds' => 'No active review period. Activate a term before enrolling subjects.',
        ]));

        $resolver = app(EnrollmentSectionResolver::class);
        $offerings = $resolver->offeringsFor($requested->keys());
        $codes = Subject::whereIn('id', $requested->keys())->pluck('code', 'id');

        $pivot = [];
        $errors = [];
        foreach ($requested as $subjectId => $sectionId) {
            [$resolved, $error] = $resolver->resolve($codes[$subjectId], $offerings[$subjectId] ?? [], $sectionId);
            if (! $error && $resolved === null) $error = $codes[$subjectId].' has no active class offering. Schedule the class before assigning this subject.';
            $error ? $errors[] = $error : $pivot[$subjectId] = ['class_section_id' => $resolved];
        }

        throw_if($errors, ValidationException::withMessages(['subjectIds' => implode(' ', $errors)]));

        return $pivot;
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $userId = $student?->user_id ?? 'NULL';

        $validated = $request->validate([
            'studentId' => [
                'required', 'string', 'max:30',
                'unique:students,student_number,'.($student?->id ?? 'NULL'),
                'unique:users,id_number,'.$userId,
            ],
            'email' => [
                'required', 'email', 'max:255',
                'unique:students,email,'.($student?->id ?? 'NULL'),
                'unique:users,email,'.$userId,
            ],
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'isIrregular' => ['nullable', 'boolean'],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'block' => ['nullable', 'string', 'max:10'],
        ]);

        $isIrregular = (bool) ($validated['isIrregular'] ?? false);

        if (! $isIrregular) {
            throw_unless($validated['year'] ?? null, ValidationException::withMessages([
                'year' => 'Select a year level for a regular student.',
            ]));
            throw_unless($validated['block'] ?? null, ValidationException::withMessages([
                'block' => 'Select a block for a regular student.',
            ]));
        }

        return [
            'student_number' => $validated['studentId'],
            'email' => $validated['email'],
            'first_name' => $validated['firstName'],
            'middle_name' => $validated['middleName'] ?? null,
            'last_name' => $validated['lastName'],
            'suffix' => $validated['suffix'] ?? null,
            'is_irregular' => $isIrregular,
            'year' => $validated['year'] ?? null,
            'block' => $validated['block'] ?? null,
        ];
    }
}
