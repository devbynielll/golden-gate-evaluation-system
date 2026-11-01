<?php

namespace App\Jobs;

use App\Mail\EvaluationReminder;
use App\Models\Evaluation;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Services\StudentEvaluationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEvaluationReminderRowsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $targets,
        private int $importBatchId,
    ) {}

    public function handle(StudentEvaluationService $evaluations): void
    {
        $batch = ImportBatch::find($this->importBatchId);
        if (! $batch) {
            return;
        }

        $period = $evaluations->activePeriod();
        $students = Student::whereIn('id', array_unique(array_column($this->targets, 'student_id')))
            ->get()
            ->keyBy('id');

        foreach ($this->targets as $target) {
            $student = $students->get($target['student_id']);

            if (! $student) {
                $batch->recordFailure($target['student_id'], 'Student no longer exists.');

                continue;
            }

            if ($target['type'] === Evaluation::TYPE_OFFICE) {
                if (!$period || !SendOfficeEvaluationReminder::pending($student,$period,$target['office_id'] ?? null)) {
                    $batch->recordSkip($student->id,'No pending applicable Office evaluations.');
                    continue;
                }
                $key="office-reminder:{$period->id}:{$student->id}";
                if (\Illuminate\Support\Facades\Cache::has($key.':sent') || !\Illuminate\Support\Facades\Cache::add($key.':queued',true,now()->addMinutes(10))) {
                    $batch->recordSkip($student->id,'Office reminder already queued or recently sent.');
                    continue;
                }
                try {
                    SendOfficeEvaluationReminder::dispatch($student->id,$period->id,$target['office_id'] ?? null,$target['actor_id'] ?? null)->onQueue('default');
                    $batch->recordSuccess();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Cache::forget($key.':queued');
                    $batch->recordFailure($student->id,'Office reminder could not be queued.');
                }
                continue;
            }

            $label = $target['type'] === Evaluation::TYPE_FACULTY
                ? 'their faculty members'
                : 'the offices on campus';

            try {
                $pending = $target['type'] === Evaluation::TYPE_FACULTY
                    ? $evaluations->pendingFacultyLabels($student, $period)
                    : [];
                Mail::to($student->email)->queue(new EvaluationReminder($student, $label, $pending));
                $batch->recordSuccess();
            } catch (\Throwable $e) {
                $batch->recordFailure($student->fullName(), $e->getMessage());
            }
        }
    }
}
