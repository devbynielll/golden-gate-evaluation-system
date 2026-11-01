<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminAccountCreated;
use App\Models\User;
use App\Models\Office;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// super admin manages department and office admins
class AdminAccountController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['search'] ?? null;

        $accounts = User::query()
            ->whereIn('role', User::MANAGED_ROLES)
            ->with('office:id,code,name')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(self::PER_PAGE, page: $filters['page'] ?? 1)
            ->withQueryString();

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => ['search' => $search],
            // Only the Department Admin role has a global singleton limit.
            'takenRoles' => User::where('role', User::ROLE_DEPARTMENT_ADMIN)->pluck('role'),
            'offices' => Office::orderBy('name')->get(['id', 'code', 'name']),
            'officeAssignments' => User::where('role', User::ROLE_OFFICE_ADMIN)->whereNotNull('office_id')->get(['id', 'office_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = Str::password(12);
        $idNumber = $data['id_number'] ?? $this->generateIdNumber($data['role']);

        try {
            DB::transaction(function () use ($data, $password, $idNumber) {
                $user = User::create([
                    ...$this->attributes($data),
                    'id_number' => $idNumber,
                    'password' => Hash::make($password),
                ]);
                // already vetted, skip email verification
                $user->markEmailAsVerified();

                // no account if the email fails
                Mail::to($user->email)->send(new AdminAccountCreated($user, $password));
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([$data['role'] === User::ROLE_DEPARTMENT_ADMIN ? 'role' : 'office_id' => 'The role, office or account details were claimed by another request. Reload and review the available assignments.']);
        } catch (\Throwable $e) {
            Log::error('Admin account creation failed: could not email login details.', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return back()->with('status', "Couldn't email the login details, so the account wasn't created. Please try again.");
        }

        Cache::flush();
        return back()->with('status', 'Account created and login details emailed.');
    }

    // role can't change after creation
    public function update(Request $request, User $account): RedirectResponse
    {
        $this->ensureManaged($account);

        $data = $this->validated($request, $account);
        try {
            DB::transaction(fn () => $account->update($this->attributes($data)));
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['office_id' => 'The office or account details were claimed by another request. Reload and review the assignment.']);
        }
        Cache::flush();

        return back()->with('status', 'Account updated.');
    }

    public function resetPassword(User $account): RedirectResponse
    {
        $this->ensureManaged($account);

        $password = Str::password(12);

        try {
            DB::transaction(function () use ($account, $password) {
                $account->update(['password' => Hash::make($password)]);

                // old password stays if the email fails
                Mail::to($account->email)->send(new AdminAccountCreated($account, $password, reset: true));
            });
        } catch (\Throwable $e) {
            Log::error('Admin password reset failed: could not email the new password.', [
                'user_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('status', "Couldn't email the new password, so it wasn't changed. Please try again.");
        }

        return back()->with('status', 'Password reset and emailed.');
    }

    public function destroy(User $account): RedirectResponse
    {
        $this->ensureManaged($account);

        $account->delete();
        Cache::flush();

        return back()->with('status', 'Account deleted.');
    }

    // only dept/office admins, never super admins or students
    private function ensureManaged(User $account): void
    {
        abort_unless(in_array($account->role, User::MANAGED_ROLES, true), 404);
    }

    private function validated(Request $request, ?User $account = null): array
    {
        $role = $account?->role ?? $request->input('role');

        $validated = $request->validate([
            'role' => [
                $account ? 'prohibited' : 'required',
                Rule::in(User::MANAGED_ROLES),
                // Preserve the existing single Department Admin rule.
                ...($role === User::ROLE_DEPARTMENT_ADMIN ? [Rule::unique('users', 'role')] : []),
            ],
            'office_id' => $role === User::ROLE_OFFICE_ADMIN
                ? ['required', 'integer', 'exists:offices,id', Rule::unique('users', 'office_id')->ignore($account?->id)]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id)],
            'id_number' => ['nullable', 'string', 'max:30', Rule::unique('users', 'id_number')->ignore($account?->id)],
        ], [
            'role.unique' => $role === User::ROLE_DEPARTMENT_ADMIN
                ? 'There is already a department admin. Only one is allowed.'
                : 'There is already an office admin. Only one is allowed.',
        ]);

        return [...$validated, 'role' => $role];
    }

    private function attributes(array $data): array
    {
        return array_filter([
            'role' => $data['role'],
            'name' => $data['name'],
            'email' => $data['email'],
            'id_number' => $data['id_number'] ?? null,
            'office_id' => $data['role'] === User::ROLE_OFFICE_ADMIN ? ($data['office_id'] ?? null) : null,
        ], fn ($value) => $value !== null);
    }

    private function generateIdNumber(string $role): string
    {
        $prefix = $role === User::ROLE_DEPARTMENT_ADMIN ? 'DPT' : 'OFC';

        do {
            $idNumber = $prefix.'-'.Str::padLeft((string) random_int(0, 999999), 6, '0');
        } while (User::where('id_number', $idNumber)->exists());

        return $idNumber;
    }
}
