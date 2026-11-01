<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEvaluationReminderRowsJob;
use App\Mail\EvaluationReminder;
use App\Models\Evaluation;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Services\AdminDashboardService;
use App\Services\StudentEvaluationService;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EvaluationReminderController extends Controller
{
    public function __construct(private AdminDashboardService $dashboard) {}

    // nudges a student by email about a still-pending faculty/office evaluation
    public function store(Request $request, Student $student, StudentEvaluationService $evaluations): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.Evaluation::TYPE_FACULTY.','.Evaluation::TYPE_OFFICE],
        ]);

        abort_if(
            $request->user()->isDepartmentAdmin() && $data['type'] === Evaluation::TYPE_OFFICE,
            403,
            'Department admins only handle faculty evaluations.',
        );

        $label = $data['type'] === Evaluation::TYPE_FACULTY
            ? 'their faculty members'
            : 'the offices on campus';

        try {
            $pending = $data['type'] === Evaluation::TYPE_FACULTY
                ? $evaluations->pendingFacultyLabels($student, $evaluations->activePeriod())
                : [];

            Mail::to($student->email)->send(new EvaluationReminder($student, $label, $pending));
        } catch (\Throwable $e) {
            Log::error('Evaluation reminder email failed.', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('status', "Couldn't send the reminder email. Please try again.");
        }

        return back()->with('status', "Reminder email sent to {$student->fullName()}.");
    }

    // queues a reminder for every student still pending, matching the feed's current filter
    public function storeBulk(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);

        $departmentId = $filters['department_id'] ?? null;
        $programId = $filters['program_id'] ?? null;

        $period = $this->dashboard->activePeriod();
        $targets = $this->dashboard->pendingReminderTargets($period, $departmentId, $programId, includeOffices: ! $request->user()->isDepartmentAdmin());

        $batch = ImportBatch::create([
            'type' => ImportBatch::TYPE_REMINDER,
            'department_id' => $departmentId,
            'program_id' => $programId,
            'total_rows' => count($targets),
            'created_by' => $request->user()->id,
        ]);

        if (empty($targets)) {
            $batch->markCompleted();

            return response()->json(['batchId' => $batch->id]);
        }

        $jobs = array_map(
            fn (array $chunk) => new SendEvaluationReminderRowsJob($chunk, $batch->id),
            array_chunk($targets, 50),
        );

        Bus::batch($jobs)
            ->onQueue('imports')
            ->then(function (Batch $busBatch) use ($batch) {
                $batch->markCompleted();
            })
            ->dispatch();

        return response()->json(['batchId' => $batch->id]);
    }
}
