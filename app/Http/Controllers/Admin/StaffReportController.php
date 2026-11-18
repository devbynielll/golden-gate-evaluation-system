<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Office, ReviewPeriod};
use App\Services\StaffReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffReportController extends Controller
{
    public function index(Request $request, StaffReportService $reports): \Symfony\Component\HttpFoundation\Response
    {
        $office = $request->user()->isOfficeAdmin() ? $request->user()->assignedOfficeOrAbort() : null;
        if ($office && $request->has('office_id')) {
            $context = $request->validate(['office_id' => ['required', 'integer']]);
            abort_unless((int) $context['office_id'] === $office->id, 403);
        }
        $filters = $request->validate([
            'office_id' => ['nullable', 'integer', 'exists:offices,id'], 'period_id' => ['nullable', 'integer', 'exists:review_periods,id'],
            'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
            'report' => ['nullable', 'in:summary,individual'], 'target_id' => ['nullable', 'required_if:report,individual', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $officeId = $office?->id ?? ($filters['office_id'] ?? null);
        $rows = $reports->rows($officeId, $filters['period_id'] ?? null, $filters['search'] ?? null);
        $notice = 'Staff results require at least five distinct student evaluations and an officially closed review period. Feedback is not disclosed.';
        if ($filters['report'] ?? null) {
            $detail = $filters['report'] === 'individual' ? $rows->firstWhere('id', $filters['target_id']) : null;
            abort_if($filters['report'] === 'individual' && ! $detail, 404);
            $period = isset($filters['period_id']) ? ReviewPeriod::findOrFail($filters['period_id']) : null;
            return response()->json([
                'subject' => 'Staff', 'reviewPeriod' => $detail['reviewPeriod'] ?? ($period ? $period->semester.' | '.$period->academic_year : 'All Semesters (separate period reports)'),
                'filterLabel' => ($officeId ? 'Office: '.Office::findOrFail($officeId)->code : 'All Offices').(!empty($filters['search']) ? ' | Search: '.$filters['search'] : ''),
                'notice' => $notice, ...($detail ? ['detail' => $detail] : ['summaryRows' => $rows->values()]),
            ])->header('Cache-Control', 'private, no-store');
        }
        $page = $filters['page'] ?? 1;
        return Inertia::render('Admin/Reports/Staff', [
            'staff' => ['data' => $rows->slice(($page - 1) * 10, 10)->values(), 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($rows->count() / 10)), 'total' => $rows->count()]],
            'filterOptions' => ['offices' => $office ? [] : Office::orderBy('name')->get(['id', 'code', 'name']), 'periods' => ReviewPeriod::orderByDesc('start_date')->get()->map(fn ($p) => ['id' => $p->id, 'label' => $p->semester.' | '.$p->academic_year])],
            'selectedFilters' => ['office_id' => $officeId, 'period_id' => $filters['period_id'] ?? null, 'search' => $filters['search'] ?? null],
            'reportNotice' => $notice,
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
