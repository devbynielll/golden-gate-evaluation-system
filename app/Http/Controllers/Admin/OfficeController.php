<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    private const CACHE_TTL = 60;

    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['search'] ?? null;
        $page = $filters['page'] ?? 1;

        $cacheKey = 'admin-offices:'.($search ?? 'all').':'.$page;

        $offices = Cache::remember($cacheKey, self::CACHE_TTL, fn () => Office::query()
            ->with('department')
            ->when($search, fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->orderBy('code')
            ->paginate(self::PER_PAGE, page: $page));

        return Inertia::render('Admin/Offices/Index', [
            'offices' => $offices,
            'filters' => ['search' => $search],
            'departments' => Department::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:20', 'unique:offices,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Office::create($data);
        Cache::flush();

        return back()->with('status', 'Office added.');
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:20', 'unique:offices,code,'.$office->id],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $office->update($data);
        Cache::flush();

        return back()->with('status', 'Office updated.');
    }

    public function destroy(Office $office): RedirectResponse
    {
        if (\App\Models\CriteriaCategory::where('office_id', $office->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'office' => 'This office has office-specific evaluation criteria and cannot be deleted. Preserve those criteria and their history first.',
            ]);
        }
        if ($office->staff()->exists() || \App\Models\StaffCriteriaCategory::where('office_id', $office->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['office' => 'This office has staff or staff criteria. Preserve its historical references instead of deleting it.']);
        }
        $office->delete();
        Cache::flush();

        return back()->with('status', 'Office deleted.');
    }
}
