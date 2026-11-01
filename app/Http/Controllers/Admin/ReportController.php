<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function faculty(Request $request): Response
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'period_id' => ['nullable', 'integer', 'exists:review_periods,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $departmentId = $filters['department_id'] ?? null;

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

    public function office(Request $request): Response
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
        ]);

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
}
