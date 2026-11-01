<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $reviewPeriod->update(['is_submission_open' => ! $reviewPeriod->is_submission_open]);

        $this->clearStatsCache();

        return back()->with('status', $reviewPeriod->is_submission_open ? 'Evaluations are now open.' : 'Evaluations are now closed.');
    }

    public function destroy(ReviewPeriod $reviewPeriod): RedirectResponse
    {
        $reviewPeriod->delete();

        $this->clearStatsCache();

        return back()->with('status', 'Review period removed.');
    }

    // cache keys vary by scope, so just clear everything
    private function clearStatsCache(): void
    {
        Cache::flush();
    }
}
