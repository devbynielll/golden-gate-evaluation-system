<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Office, OfficeStaff, ReviewPeriod, StaffCriteriaQuestion, StaffEvaluation, Student};
use App\Services\StudentEvaluationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffEvaluationController extends Controller
{
    public function store(Request $request, OfficeStaff $staff, StudentEvaluationService $periods): RedirectResponse
    {
        $student = $request->user()->student;
        abort_unless($student, 403);
        $period = $periods->activePeriod();
        if (! $period || ! $period->is_submission_open) {
            throw ValidationException::withMessages(['staff' => 'Evaluations are closed right now.']);
        }
        try {
            DB::transaction(function () use ($request, $student, $period, $staff, $periods) {
                ReviewPeriod::orderBy('id')->sharedLock()->get();
                $current = $periods->activePeriod();
                if (! $current || $current->id !== $period->id || ! $current->is_submission_open) {
                    throw ValidationException::withMessages(['staff' => 'This evaluation period changed or closed. Reload the evaluation page.']);
                }
                $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $office = Office::whereKey($staff->office_id)->sharedLock()->firstOrFail();
                $staff = OfficeStaff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
                abort_unless($staff->office_id === $office->id, 403);
                abort_unless($office->department_id === null || $office->department_id === $student->department()?->id, 403);
                if (! $staff->is_active) throw ValidationException::withMessages(['staff' => 'This staff member is no longer available for evaluation.']);
                if (StaffEvaluation::where('student_id', $student->id)->where('review_period_id', $period->id)->where('office_staff_id', $staff->id)->exists()) {
                    throw ValidationException::withMessages(['staff' => 'You already submitted this staff evaluation.']);
                }
                $questions = StaffCriteriaQuestion::whereHas('category', fn ($q) => $q->forQuestionnaire('staff', $office->id))
                    ->with('category')->orderBy('criteria_category_id')->orderBy('id')->get();
                if ($questions->isEmpty()) throw ValidationException::withMessages(['staff' => 'No staff criteria are available for this office yet.']);
                $data = $request->validate(['ratings' => ['required', 'array', 'size:'.$questions->count()],
                    'ratings.*' => ['required', 'integer', 'min:1', 'max:4'], 'comments' => ['nullable', 'string', 'max:300']]);
                $keys = collect(array_keys($data['ratings']))->map(fn ($id) => (string) $id)->sort()->values()->all();
                $expected = $questions->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
                if ($keys !== $expected) throw ValidationException::withMessages(['ratings' => 'The staff questionnaire changed or contains invalid questions. Reload the form.']);
                $evaluation = StaffEvaluation::create([
                    'review_period_id' => $period->id, 'student_id' => $student->id, 'office_staff_id' => $staff->id, 'office_id' => $office->id,
                    'staff_name' => $staff->name, 'staff_position' => $staff->position, 'office_code' => $office->code, 'office_name' => $office->name,
                    'academic_year' => $current->academic_year, 'semester' => $current->semester, 'submitted_at' => now(), 'comments' => $data['comments'] ?? null,
                ]);
                foreach ($questions as $question) {
                    $question->freezeForHistory();
                    $category = $question->category;
                    $evaluation->answers()->create([
                        'staff_criteria_question_id' => $question->id, 'staff_criteria_category_id' => $category->id,
                        'category_name' => $category->name, 'section_number' => $category->section_number, 'is_office_specific' => $category->office_id !== null,
                        'question_text' => $question->text, 'question_order' => $question->order, 'rating' => $data['ratings'][$question->id],
                    ]);
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['staff' => 'You already submitted this staff evaluation.']);
        }
        return back()->with('status', 'Staff evaluation submitted, thank you!');
    }
}
