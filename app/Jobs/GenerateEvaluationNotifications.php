<?php

namespace App\Jobs;

use App\Services\EvaluationNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEvaluationNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $periodId) { $this->afterCommit(); }

    public function handle(EvaluationNotificationService $notifications): void
    {
        $notifications->generate($this->periodId);
    }
}
