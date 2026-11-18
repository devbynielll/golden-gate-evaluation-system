<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Department;
use App\Models\ReviewPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function faculty(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'period_id' => ['nullable', 'integer', 'exists:review_periods,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'report' => ['nullable', 'in:summary,individual'],
            'target_id' => ['nullable', 'required_if:report,individual', 'integer', 'min:1'],
        ]);

        $departmentId = $filters['department_id'] ?? null;

        if ($filters['report'] ?? null) {
            $targetId = $filters['report'] === 'individual' ? (int) $filters['target_id'] : null;
            $rows = $this->reports->facultyReport($departmentId, $filters['period_id'] ?? null, 1, true, $targetId)['data'];

            return $this->reportPayload('Faculty', $filters, $rows,
                $departmentId ? 'Department: '.Department::findOrFail($departmentId)->code : 'All Departments');
        }

        return Inertia::render('Admin/Reports/Faculty', [
            'faculty' => $this->reports->facultyReport(
                $departmentId,
                $filters['period_id'] ?? null,
                $filters['page'] ?? 1,
            ),
            'filterOptions' => $this->reports->filterOptions(),
            'selectedFilters' => [
                'department_id' => $departmentId,
                'period_id' => $filters['period_id'] ?? null,
            ],
        ]);
    }

    public function office(Request $request): Response|JsonResponse
    {
        $office = $request->user()->isOfficeAdmin() ? $request->user()->assignedOfficeOrAbort() : null;
        if ($office && $request->has('office_id')) {
            $context = $request->validate(['office_id' => ['required', 'integer']]);
            abort_unless((int) $context['office_id'] === $office->id, 403);
        }
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:review_periods,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'report' => ['nullable', 'in:summary,individual'],
            'target_id' => ['nullable', 'required_if:report,individual', 'integer', 'min:1'],
        ]);

        if ($filters['report'] ?? null) {
            $targetId = $filters['report'] === 'individual' ? (int) $filters['target_id'] : null;
            abort_if($office && $targetId !== null && $targetId !== $office->id, 403);
            $rows = $this->reports->officeReport($filters['search'] ?? null, $filters['period_id'] ?? null, 1, $office?->id, true, $targetId)['data'];

            return $this->reportPayload('Office', $filters, $rows,
                ($office ? 'Office: '.$office->name : 'All Offices').(! empty($filters['search']) ? ' | Search: '.$filters['search'] : ''));
        }

        return Inertia::render('Admin/Reports/Office', [
            'offices' => $this->reports->officeReport(
                $filters['search'] ?? null,
                $filters['period_id'] ?? null,
                $filters['page'] ?? 1,
                $office?->id,
            ),
            'filterOptions' => $office
                ? ['departments' => [], 'periods' => $this->reports->filterOptions()['periods']]
                : $this->reports->filterOptions(),
            'selectedFilters' => [
                'search' => $filters['search'] ?? null,
                'period_id' => $filters['period_id'] ?? null,
            ],
        ]);
    }

    private function reportPayload(string $subject, array $filters, $rows, string $filterLabel): JsonResponse
    {
        $individual = $filters['report'] === 'individual';
        abort_if($individual && $rows->isEmpty(), 404);
        $period = isset($filters['period_id']) ? ReviewPeriod::findOrFail($filters['period_id']) : null;

        return response()->json([
            'subject' => $subject,
            'reviewPeriod' => $period ? "{$period->semester} | {$period->academic_year}" : 'All Semesters',
            'filterLabel' => $filterLabel,
            ...($individual ? ['detail' => $rows->first()] : ['summaryRows' => $rows->values()]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
