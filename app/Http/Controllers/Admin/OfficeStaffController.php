<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Office, OfficeStaff, User};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\{Inertia, Response};

class OfficeStaffController extends Controller
{
    private function authorizeOffice(Request $request, Office $office): void
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isOfficeAdmin(), 403);
        if ($request->user()->isOfficeAdmin()) abort_unless($request->user()->assignedOfficeOrAbort()->id === $office->id, 403);
        if ($request->has('office_id')) {
            $data = $request->validate(['office_id' => ['required', 'integer']]);
            abort_unless((int) $data['office_id'] === $office->id, 403);
        }
    }

    public function index(Request $request, ?Office $office = null): Response
    {
        $office ??= $request->user()->assignedOfficeOrAbort();
        $this->authorizeOffice($request, $office);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,archived,all'], 'page' => ['nullable', 'integer', 'min:1']]);
        $status = $filters['status'] ?? 'active';
        $staff = $office->staff()->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('position', 'like', "%{$search}%")))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
        return Inertia::render('Admin/Offices/Staff', ['office' => $office->only(['id', 'code', 'name']), 'staff' => $staff,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status]]);
    }

    public function store(Request $request, Office $office): RedirectResponse
    {
        return $this->write($request, $office, null);
    }

    public function update(Request $request, Office $office, OfficeStaff $staff): RedirectResponse
    {
        return $this->write($request, $office, $staff);
    }

    public function archive(Request $request, Office $office, OfficeStaff $staff): RedirectResponse
    {
        return $this->write($request, $office, $staff, true);
    }

    private function write(Request $request, Office $office, ?OfficeStaff $staff, bool $archive = false): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $office, $staff, $archive) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                Office::whereKey($office->id)->lockForUpdate()->firstOrFail();
                $this->authorizeOffice($request, $office);
                $record = $staff ? OfficeStaff::whereKey($staff->id)->lockForUpdate()->firstOrFail() : new OfficeStaff;
                abort_if($record->exists && $record->office_id !== $office->id, 404);
                if ($archive) { $record->is_active = false; $record->save(); return; }
                foreach (['name', 'position'] as $field) {
                    if (is_string($request->input($field))) $request->merge([$field => trim(preg_replace('/\s+/u', ' ', $request->input($field)))]);
                }
                $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'position' => ['required', 'string', 'max:255']]);
                $key = OfficeStaff::identityKey($data['name'], $data['position']);
                if (OfficeStaff::where('office_id', $office->id)->where('identity_key', $key)->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                    throw ValidationException::withMessages(['name' => 'This name and position already exist in this office, including archived records.']);
                }
                $record->fill($data);
                $record->office_id = $office->id;
                $record->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This name and position already exist in this office, including archived records.']);
        }
        return back()->with('status', $archive ? 'Staff member archived. Historical references are preserved.' : ($staff ? 'Staff member updated.' : 'Staff member added.'));
    }
}
