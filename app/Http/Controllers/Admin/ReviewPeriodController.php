<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewPeriodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1st Semester,2nd Semester,Summer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $isFirst = ! ReviewPeriod::exists();
        $period = ReviewPeriod::create($data);

        // pre-term data goes to the first period
        if ($isFirst) {
            DB::table('class_subjects')->whereNull('review_period_id')->update(['review_period_id' => $period->id]);
            DB::table('student_subject_enrollments')->whereNull('review_period_id')->update(['review_period_id' => $period->id]);
        }

        $this->clearStatsCache();

        return back()->with('status', 'Review period added.');
    }

    public function update(Request $request, ReviewPeriod $reviewPeriod): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $reviewPeriod) {
                // Match submission lock ordering so identity checks include in-flight evaluations.
                $period = ReviewPeriod::orderBy('id')->lockForUpdate()->get()->firstWhere('id', $reviewPeriod->id);
                abort_unless($period, 404);

                $data = $request->validate([
                    'academic_year' => ['required', 'string', 'max:20', Rule::unique('review_periods')
                        ->where('semester', $request->input('semester'))->ignore($period->id)],
                    'semester' => ['required', 'in:1st Semester,2nd Semester,Summer'],
                    'start_date' => ['required', 'date_format:Y-m-d'],
                    'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
                ], [
                    'academic_year.unique' => 'A review period already exists for this Academic Year and Semester.',
                    'start_date.date_format' => 'Enter a valid Start Date.',
                    'end_date.date_format' => 'Enter a valid End Date.',
                    'end_date.after' => 'End Date must be after Start Date.',
                ]);

                if ($period->evaluations()->exists() || $period->staffEvaluations()->exists()) {
                    $errors = [];
                    foreach (['academic_year' => 'Academic Year', 'semester' => 'Semester'] as $field => $label) {
                        if ($data[$field] !== $period->$field) {
                            $errors[$field] = "$label cannot be changed because this period has linked evaluations. Dates may still be corrected or extended.";
                        }
                    }
                    if ($errors) throw ValidationException::withMessages($errors);
                }

                // Only these four fields are writable; activation and submission status are untouched.
                $period->update($data);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'academic_year' => 'A review period already exists for this Academic Year and Semester.',
            ]);
        }

        $this->clearStatsCache();

        return back()->with('status', 'Review period updated. Submission status is unchanged.');
    }

    public function activate(ReviewPeriod $reviewPeriod): RedirectResponse
    {
        // only one period is ever active at a time
        ReviewPeriod::where('is_active', true)->update(['is_active' => false]);
        $reviewPeriod->update(['is_active' => true]);

        $this->clearStatsCache();

        return back()->with('status', 'Active period updated.');
    }

    public function toggleSubmission(ReviewPeriod $reviewPeriod): RedirectResponse
    {
        DB::transaction(function () use ($reviewPeriod) {
            $period = ReviewPeriod::whereKey($reviewPeriod->id)->lockForUpdate()->firstOrFail();
            $period->update(['is_submission_open' => ! $period->is_submission_open]);
            $reviewPeriod->setAttribute('is_submission_open', $period->is_submission_open);
            if (! $period->is_submission_open) {
                \App\Jobs\GenerateEvaluationNotifications::dispatch($period->id);
            }
        }, 3);

        $this->clearStatsCache();

        return back()->with('status', $reviewPeriod->is_submission_open ? 'Evaluations are now open.' : 'Evaluations are now closed.');
    }

    public function destroy(ReviewPeriod $reviewPeriod): RedirectResponse
    {
        DB::transaction(function () use ($reviewPeriod) {
            $period = ReviewPeriod::whereKey($reviewPeriod->id)->lockForUpdate()->firstOrFail();
            if ($period->staffEvaluations()->exists()) {
                throw ValidationException::withMessages(['period' => 'This review period has staff evaluation history and cannot be deleted.']);
            }
            $period->delete();
        }, 3);

        $this->clearStatsCache();

        return back()->with('status', 'Review period removed.');
    }

    // cache keys vary by scope, so just clear everything
    private function clearStatsCache(): void
    {
        Cache::flush();
    }
}
