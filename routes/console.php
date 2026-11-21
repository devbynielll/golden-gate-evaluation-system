<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Repair missed dispatches/retries and deliver to newly authorized recipients without
// observing individual submission events. Existing queue workers process the batches.
\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\ReviewPeriod::where('is_submission_open', false)->whereHas('evaluations', fn ($query) => $query->whereNotNull('submitted_at'))->orderBy('id')->chunkById(100, function ($periods) {
        foreach ($periods as $period) \App\Jobs\GenerateEvaluationNotifications::dispatch($period->id);
    });
})->name('evaluation-notification-reconciliation')->hourly()->withoutOverlapping();
