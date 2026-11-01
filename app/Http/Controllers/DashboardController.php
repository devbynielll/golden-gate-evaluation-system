<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\ReviewPeriod;
use App\Services\AdminDashboardService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboard,
        private ReportService $reports,
    ) {}

    // same URL for every role, different page
    public function index(Request $request): Response
    {
        $user = $request->user();
        return match (true) {
            $user->isOfficeAdmin() => $this->office($request),
            $user->isAdmin() => $this->admin($request),
            default => Inertia::render('Student/Dashboard'),
        };
    }

    // super admin and department admin: same page, department admin just has no office data
    private function admin(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $departmentId = $filters['department_id'] ?? null;
        $programId = $filters['program_id'] ?? null;

        $period = $this->dashboard->activePeriod();
        $feed = $this->dashboard->feed($period, $departmentId, $programId, $filters['page'] ?? 1);
        $stats = $this->dashboard->stats($period);
        $departmentBreakdown = $this->dashboard->departmentBreakdown($period);

        // offices aren't a department admin's concern
        $showOffices = ! $user->isDepartmentAdmin();
        if (! $showOffices) {
            unset($stats['totalOffices'], $stats['officePending'], $stats['officeProgress']);
            $departmentBreakdown = array_map(function (array $department) {
                unset($department['officeProgress']);

                return $department;
            }, $departmentBreakdown);

            $feed['data'] = $feed['data']->map(function (array $student) {
                $student['submissions'] = array_values(array_filter(
                    $student['submissions'],
                    fn (array $submission) => $submission['type'] !== Evaluation::TYPE_OFFICE,
                ));

                return $student;
            });
        }

        return Inertia::render('Admin/Dashboard', [
            ...$stats,
            'showOffices' => $showOffices,
            'departmentBreakdown' => $departmentBreakdown,
            'evaluations' => $feed,
            'filterOptions' => $this->dashboard->filterOptions(),
            'selectedFilters' => [
                'department_id' => $departmentId,
                'program_id' => $programId,
            ],
            'periods' => ReviewPeriod::orderByDesc('start_date')->get(),
            'canManagePeriods' => $user->isSuperAdmin(),
        ]);
    }

    private function validateOfficeContext(Request $request, int $officeId): void
    {
        if ($request->has('office_id')) {
            $data = $request->validate(['office_id' => ['required', 'integer']]);
            abort_unless((int) $data['office_id'] === $officeId, 403);
        }
    }

    // office admin: assigned office only, active period
    private function office(Request $request): Response
    {
        $office = $request->user()->assignedOfficeOrAbort();
        $this->validateOfficeContext($request, $office->id);
        $period = $this->dashboard->activePeriod();

        return Inertia::render('Admin/OfficeDashboard', [
            'summary' => $this->reports->officeSummary($office, $period),
        ]);
    }
}
