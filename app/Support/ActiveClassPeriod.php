<?php

namespace App\Support;

use App\Models\ReviewPeriod;
use Illuminate\Validation\ValidationException;

// Normal Class writes must never fall back to a historical or future period.
final class ActiveClassPeriod
{
    public static function resolve(?int $requested): int
    {
        $active = ReviewPeriod::activeId();
        if (! $active) {
            throw ValidationException::withMessages(['termId' => 'No active evaluation period. Ask the Super Admin to activate one before scheduling classes.']);
        }
        if ($requested !== null && $requested !== $active) {
            throw ValidationException::withMessages(['termId' => 'The active evaluation period changed or this schedule is read-only. Reload the Classes page before saving.']);
        }

        return $active;
    }

    public static function lock(int $periodId): void
    {
        // Recheck inside the write transaction, before section/offering locks.
        if (! ReviewPeriod::whereKey($periodId)->where('is_active', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['termId' => 'The active evaluation period changed. Reload the Classes page before saving.']);
        }
    }
}
