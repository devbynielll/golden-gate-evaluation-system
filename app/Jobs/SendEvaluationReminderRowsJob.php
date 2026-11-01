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
