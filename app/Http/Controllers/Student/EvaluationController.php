<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\ClassSection;
use App\Models\CriteriaQuestion;
use App\Models\ReviewPeriod;
use App\Models\Evaluation;
use App\Models\Office;
use App\Models\Student;
use App\Services\StudentEvaluationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function __construct(private StudentEvaluationService $service) {}

    public function index(Request $request): Response
    {
        $student = $this->studentOrAbort($request);

        $filters = $request->validate([
            'faculty_page' => ['nullable', 'integer', 'min:1'],
            'office_page' => ['nullable', 'integer', 'min:1'],
            'staff_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $period = $this->service->activePeriod();
        $board = $this->service->board(
            $student,
            $period,
            $filters['faculty_page'] ?? 1,
            $filters['office_page'] ?? 1,
        );

        return Inertia::render('Student/Evaluation', [
            ...$board,
            ...app(\App\Services\StaffEvaluationService::class)->board($student, $period, $filters['staff_page'] ?? 1),
            'isSubmissionOpen' => $period?->is_submission_open ?? false,
        ]);
    }

    public function storeFaculty(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $student = $this->studentOrAbort($request);

        // same rule as the board
        abort_unless($student->offerings()->whereKey($classSubject->id)->exists(), 403);
        abort_unless($classSubject->faculty_id, 404);

        return $this->submit($request, $student, Evaluation::TYPE_FACULTY, [
            'class_subject_id' => $classSubject->id,
        ], $classSubject);
    }

    public function storeOffice(Request $request, Office $office): RedirectResponse
    {
        $student = $this->studentOrAbort($request);

        // own department, or an office open to everyone
        abort_unless(
            $office->department_id === null || $office->department_id === $student->department()?->id,
            403,
        );

        return $this->submit($request, $student, Evaluation::TYPE_OFFICE, [
            'office_id' => $office->id,
        ]);
    }

    private function submit(Request $request, Student $student, string $type, array $target, ?ClassSubject $classSubject = null): RedirectResponse
    {
        $period = $this->service->activePeriod();

        if (! $period || ! $period->is_submission_open) {
            return back()->with('status', 'Evaluations are closed right now.');
        }

        $alreadySubmitted = Evaluation::where('student_id', $student->id)
            ->where('review_period_id', $period->id)
            ->where('evaluatee_type', $type)
            ->where($target)
            ->whereNotNull('submitted_at')
            ->exists();

        if ($alreadySubmitted) {
            return back()->with('status', 'You already submitted this evaluation.');
        }

        try {
            DB::transaction(function () use ($request, $student, $period, $type, $target, $classSubject) {
                // Concurrent submissions may read together; questionnaire/period
                // changes take an exclusive lock on these same rows.
                ReviewPeriod::orderBy('id')->sharedLock()->get();
                $current = $this->service->activePeriod();
                abort_unless($current && $current->id === $period->id && $current->is_submission_open, 422);
                $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $questionIds = CriteriaQuestion::whereHas('category', fn ($q) => $q->forQuestionnaire($type, $target['office_id'] ?? null))->pluck('id');
                $data = $request->validate([
                    'ratings' => ['required', 'array', 'size:'.$questionIds->count()],
                    'ratings.*' => ['required', 'integer', 'min:1', 'max:4'],
                    'comments' => ['nullable', 'string', 'max:2000'],
                ]);
                // Compare canonical IDs exactly; e.g. "01" must not alias question 1.
                $submittedIds = collect(array_keys($data['ratings']))->map(fn ($id) => (string) $id)->sort()->values();
                abort_unless($submittedIds->all() === $questionIds->map(fn ($id) => (string) $id)->sort()->values()->all(), 422);
                if ($classSubject) {
                    // Same lock order as Class editing: section, then offering.
                    ClassSection::whereKey($classSubject->class_section_id)->lockForUpdate()->firstOrFail();
                    $offering = ClassSubject::whereKey($classSubject->id)->lockForUpdate()->firstOrFail();
                    abort_unless($student->offerings()->whereKey($offering->id)->exists(), 403);
                    abort_unless($offering->faculty_id, 404);
                }
                $evaluation = Evaluation::create([
                    'review_period_id' => $period->id,
                    'student_id' => $student->id,
                    'evaluatee_type' => $type,
                    'comments' => $data['comments'] ?? null,
                    'submitted_at' => now(),
                    ...$target,
                ]);

                foreach ($data['ratings'] as $questionId => $rating) {
                    $evaluation->answers()->create([
                        'criteria_question_id' => $questionId,
                        'rating' => $rating,
                    ]);
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // double submit
            return back()->with('status', 'You already submitted this evaluation.');
        }

        $this->service->forget();

        return back()->with('status', 'Evaluation submitted, thank you!');
    }

    private function studentOrAbort(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student, 403, 'Your account is not linked to a student record yet.');

        return $student;
    }
}
