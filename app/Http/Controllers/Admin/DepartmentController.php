<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    private const CACHE_TTL = 60;

    public function index(Request $request): Response
    {
        $search = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ])['search'] ?? null;

        $cacheKey = 'admin-departments:'.($search ?? 'all');

        $departments = Cache::remember($cacheKey, self::CACHE_TTL, fn () => Department::query()
            ->withCount('programs')
            ->when($search, fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->orderBy('code')
            ->get());

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create($data);
        $this->clearCache();

        return back()->with('status', 'Department added.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code,'.$department->id],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->update($data);
        $this->clearCache();

        return back()->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();
        $this->clearCache();

        return back()->with('status', 'Department deleted.');
    }

    // cached per search term, a write just flushes everything
    private function clearCache(): void
    {
        Cache::flush();
    }
}
