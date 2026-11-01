<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Office;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// faculty/office performance numbers for the reports page
class ReportService
{
    private const CACHE_TTL = 60;

    private const PER_PAGE = 10;

    // student ratings are submitted on a 1-4 scale; reports keep that same 1-4 scale

    public function filterOptions(?int $departmentId = null): array
    {
        return Cache::remember('admin-reports-filters:'.($departmentId ?? 'all'), self::CACHE_TTL, fn () => [
            'departments' => Department::orderBy('name')
                ->when($departmentId, fn ($q) => $q->whereKey($departmentId))
                ->get(['id', 'code', 'name']),
            'periods' => ReviewPeriod::orderByDesc('start_date')->get()->map(fn ($p) => [
                'id' => $p->id,
                'label' => "{$p->semester} | {$p->academic_year}",
            ]),
        ]);
    }

    public function facultyReport(?int $departmentId, ?int $periodId, int $page): array
    {
        $cacheKey = "admin-reports-faculty:{$departmentId}:{$periodId}:{$page}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId, $periodId, $page) {
            $paginator = Faculty::query()
                ->with('program.department')
                ->when($departmentId, fn ($q) => $q->whereHas(
                    'program',
                    fn ($p) => $p->where('department_id', $departmentId),
                ))
                ->orderBy('name')
                ->paginate(self::PER_PAGE, page: $page);

            $facultyIds = $paginator->getCollection()->pluck('id');
            $classSubjectsByFaculty = ClassSubject::whereIn('faculty_id', $facultyIds)
                ->get()
                ->groupBy('faculty_id');

            $rows = $paginator->getCollection()->map(function (Faculty $faculty) use ($classSubjectsByFaculty, $periodId) {
                $classSubjectIds = ($classSubjectsByFaculty->get($faculty->id) ?? collect())->pluck('id');
                $stats = $this->stats($classSubjectIds, 'class_subject_id', $periodId);

                return [
                    'id' => $faculty->id,
                    'name' => $faculty->name,
                    'facultyId' => $faculty->faculty_id,
                    'department' => $faculty->program?->department?->code,
                    ...$stats,
                ];
            })->values();

            return ['data' => $rows, 'meta' => $this->meta($paginator)];
        });
    }

    public function officeReport(?string $search, ?int $periodId, int $page, ?int $officeId = null): array
    {
        $cacheKey = 'admin-reports-office:'.($officeId ?? 'all').':'.($search ?? 'all').":{$periodId}:{$page}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($search, $periodId, $page, $officeId) {
            $paginator = Office::query()
                ->when($officeId !== null, fn ($q) => $q->whereKey($officeId))
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(self::PER_PAGE, page: $page);

            $rows = $paginator->getCollection()->map(function (Office $office) use ($periodId) {
                $stats = $this->stats(collect([$office->id]), 'office_id', $periodId);

                return ['id' => $office->id, 'name' => $office->name, ...$stats];
            })->values();

            return ['data' => $rows, 'meta' => $this->meta($paginator)];
        });
    }

    public function officeSummary(Office $office, ?ReviewPeriod $period): array
    {
        $cacheKey = 'admin-office-summary:'.$office->id.':'.($period?->id ?? 'none');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($office, $period) {
            $students = Student::query()->when($office->department_id !== null, fn ($query) => $query
                ->whereHas('program', fn ($program) => $program->where('department_id', $office->department_id)));
            $expected = $period ? (clone $students)->count() : 0;
            $completed = $period ? Evaluation::where('office_id', $office->id)
                ->where('review_period_id', $period->id)->whereNotNull('submitted_at')
                ->whereIn('student_id', (clone $students)->select('id'))->distinct()->count('student_id') : 0;
            $stats = $period ? $this->stats(collect([$office->id]), 'office_id', $period->id)
                : ['rating' => 0, 'responses' => 0, 'status' => 'No Data', 'feedback' => ''];

            return ['officeName' => $office->name, 'offices' => 1, 'term' => $period?->label(),
                'expected' => $expected, 'pending' => max(0, $expected - $completed),
                'progress' => $expected ? (int) round($completed / $expected * 100) : 0, ...$stats];
        });
    }

    // summary numbers across every office, for the office admin's home
    // $dashboardStats is AdminDashboardService::stats() — it already works out expected vs pending
    public function allOfficesSummary(?ReviewPeriod $period, array $dashboardStats): array
    {
        $cacheKey = 'admin-offices-summary:'.($period?->id ?? 'none');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period, $dashboardStats) {
            // no active term = nothing to report
            $stats = $period
                ? $this->stats(Office::pluck('id'), 'office_id', $period->id)
                : ['rating' => 0, 'responses' => 0, 'status' => 'No Data', 'feedback' => ''];

            return [
                'offices' => $dashboardStats['totalOffices'],
                'term' => $period?->label(),
                'expected' => $stats['responses'] + $dashboardStats['officePending'],
                'pending' => $dashboardStats['officePending'],
                'progress' => $dashboardStats['officeProgress'],
                ...$stats,
            ];
        });
    }

    // average rating, response count, status, and feedback for a set of evaluation targets
    private function stats($targetIds, string $column, ?int $periodId): array
    {
        $evaluations = Evaluation::whereIn($column, $targetIds)
            ->whereNotNull('submitted_at')
            ->when($periodId, fn ($q) => $q->where('review_period_id', $periodId))
            ->with('answers')
            ->get();

        $ratings = $evaluations->flatMap->answers->pluck('rating');
        $avg = $ratings->isNotEmpty() ? $ratings->avg() : 0;
        $feedback = $evaluations->pluck('comments')->filter()->implode("\n");

        return [
            'rating' => round($avg, 2),
            'responses' => $evaluations->count(),
            'status' => $this->statusFor($avg),
            'feedback' => Str::limit($feedback, 1000),
        ];
    }

    // official interpretation for the 1.00-4.00 overall rating scale
    private function statusFor(float $rating): string
    {
        return match (true) {
            $rating >= 3.51 => 'Very Satisfactory',
            $rating >= 2.51 => 'Satisfactory',
            $rating >= 1.51 => 'Unsatisfactory',
            $rating > 0 => 'Very Unsatisfactory',
            default => 'No Data',
        };
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
