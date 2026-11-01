<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

// backs the "Forgot Password?" modal
class PasswordResetCodeController extends Controller
{
    private const TTL_MINUTES = 10;

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        // never confirm or deny whether the email exists
        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            Mail::to($user->email)->send(new PasswordResetCode($code));
        }

        return response()->json(['status' => 'If that email exists, a code was sent.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);

        return DB::transaction(function () use ($request) {
            $row = DB::table('password_reset_tokens')->where('email', $request->email)->lockForUpdate()->first();

            if (! $this->validTimestamp($row) || ! Hash::check($request->code, $row->token)) {
                throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
            }

            // code is spent, issue a one-time token for the reset step
            $resetToken = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($resetToken), 'created_at' => now()],
            );

            return response()->json(['reset_token' => $resetToken]);
        }, 3);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string', 'size:64', 'alpha_num:ascii'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        return DB::transaction(function () use ($request) {
            $row = DB::table('password_reset_tokens')->where('email', $request->email)->lockForUpdate()->first();

            if (! $this->validTimestamp($row) || ! Hash::check($request->reset_token, $row->token)) {
                throw ValidationException::withMessages(['reset_token' => 'This reset session expired. Please start again.']);
            }

            $user = User::where('email', $request->email)->firstOrFail();
            $user->forceFill(['password' => Hash::make($request->password)])->save();

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json(['status' => 'Password reset successfully.']);
        }, 3);
    }

    private function validTimestamp(?object $row): bool
    {
        if (! $row || ! $row->created_at) return false;
        try {
            $created = \Carbon\CarbonImmutable::parse($row->created_at);
            $now = \Carbon\CarbonImmutable::now();
            return $created->lessThanOrEqualTo($now)
                && $created->greaterThan($now->subMinutes(self::TTL_MINUTES));
        } catch (\Throwable) {
            return false;
        }
    }
}
