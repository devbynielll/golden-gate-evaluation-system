<?php

namespace App\Services;

use App\Models\{OfficeStaff, ReviewPeriod, StaffEvaluation};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffReportService
{
    public const MINIMUM_RESPONSES = 5;
    public const INSUFFICIENT = 'Insufficient responses to protect student anonymity.';
    public const OPEN = 'Staff results are withheld until the review period is officially closed.';

    public function __construct(private ReportService $ratings) {}

    // No result cache: every page/API/download rechecks the live release flag.
    public function rows(?int $officeId, ?int $periodId, ?string $search)
    {
        return DB::transaction(function () use ($officeId, $periodId, $search) {
            $periods = ReviewPeriod::when($periodId !== null, fn ($q) => $q->whereKey($periodId))
                ->orderBy('id')->sharedLock()->get();
            $staff = OfficeStaff::with('office')->when($officeId !== null, fn ($q) => $q->where('office_id', $officeId))
                ->orderBy('name')->orderBy('id')->get(); // Include archived assignments for historical reports.
            $evaluations = StaffEvaluation::whereIn('office_staff_id', $staff->pluck('id'))->whereIn('review_period_id', $periods->pluck('id'))
                ->with('answers')->get()->groupBy(fn ($evaluation) => $evaluation->office_staff_id.':'.$evaluation->review_period_id);
            $rows = collect();
            foreach ($staff as $member) {
                foreach ($periods as $period) {
                    $items = $evaluations->get($member->id.':'.$period->id, collect());
                    $answers = $items->flatMap->answers;
                    $sections = $answers->groupBy(fn ($answer) => $answer->staff_criteria_category_id.':'.$answer->section_number.':'.$answer->category_name.':'.(int) $answer->is_office_specific);
                    $count = $items->pluck('student_id')->unique()->count();
                    $safeSections = $sections->isNotEmpty() && $sections->every(fn ($values) => $values->pluck('staff_evaluation_id')->unique()->count() >= self::MINIMUM_RESPONSES);
                    $released = ! $period->is_submission_open && $count >= self::MINIMUM_RESPONSES && $safeSections;
                    $message = $period->is_submission_open ? self::OPEN : self::INSUFFICIENT;
                    // Suppressed entries use roster metadata, never small-cohort snapshot variants or counts.
                    $row = [
                        'id' => hash('sha256', $member->id.':'.$period->id),
                        'name' => $released ? $items->pluck('staff_name')->unique()->implode(' / ') : $member->name,
                        'position' => $released ? $items->pluck('staff_position')->unique()->implode(' / ') : $member->position,
                        'officeName' => $released ? $items->pluck('office_name')->unique()->implode(' / ') : $member->office->name,
                        'officeCode' => $released ? $items->pluck('office_code')->unique()->implode(' / ') : $member->office->code,
                        'reviewPeriod' => $released ? $items->map(fn ($item) => $item->semester.' | '.$item->academic_year)->unique()->implode(' / ') : $period->semester.' | '.$period->academic_year,
                        'released' => $released, 'disclosureMessage' => $released ? '' : $message,
                        'rating' => $released ? round($answers->avg('rating'), 2) : null,
                        'responses' => $released ? $count : null,
                        'status' => $released ? $this->ratings->statusFor($answers->avg('rating')) : 'Withheld',
                        'sections' => $released ? $sections->map(function ($values) {
                            $answer = $values->first();
                            return ['section' => $answer->section_number,
                                'label' => "Section {$answer->section_number}: {$answer->category_name}".($answer->is_office_specific ? ' (Office-specific)' : ''),
                                'rating' => round($values->avg('rating'), 2), 'answeredItems' => $values->count()];
                        })->sortBy('section')->values()->all() : [],
                    ];
                    // Search selects entire staff/period reports, never subsets of respondents.
                    $searchable = $member->name.' '.$member->position.' '.$member->office->name.' '.$member->office->code.' '.$row['name'].' '.$row['position'].' '.$row['officeName'].' '.$row['officeCode'];
                    if ($search === null || Str::contains(mb_strtolower($searchable), mb_strtolower($search))) $rows->push($row);
                }
            }
            return $rows;
        }, 3);
    }
}
