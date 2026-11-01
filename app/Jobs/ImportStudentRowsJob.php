<?php

namespace App\Jobs;

use App\Mail\StudentAccountCreated;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentEvaluationService;
use App\Services\StudentImportAnalyzer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportStudentRowsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $records,
        private int $importBatchId,
        private int $programId,
        private bool $updateExisting = false,
        private bool $regularOnly = false,
        private bool $irregularOnly = false,
        private bool $mixed = false,
    ) {}

    public function handle(StudentImportAnalyzer $analyzer): void
    {
        if ($this->batchId && $this->batch()?->cancelled()) {
            return;
        }

        $batch = ImportBatch::find($this->importBatchId);
        $program = Program::find($this->programId);
        if (! $batch || ! $program) {
            return;
        }

        // re-checked here, data may have changed since the preview
        $results = $analyzer->analyze($program, $this->records, $this->updateExisting, $this->regularOnly ?? false, $this->irregularOnly ?? false, $this->mixed ?? false);
        $enrolledAny = false;

        foreach ($results as $result) {
            $row = $result['rows'][0];

            try {
                match ($result['action']) {
                    StudentImportAnalyzer::ACTION_CREATE => $this->create($program, $result, $batch),
                    StudentImportAnalyzer::ACTION_ENROLL => $this->enroll($result, $batch),
                    StudentImportAnalyzer::ACTION_SKIP => $batch->recordSkip($row, implode(' ', $result['messages'])),
                    default => $batch->recordFailure($row, implode(' ', $result['messages'])),
                };
                $enrolledAny = $enrolledAny || $result['action'] === StudentImportAnalyzer::ACTION_CREATE || $result['pivot'] !== [];
            } catch (\Throwable $e) {
                $batch->recordFailure($row, $e->getMessage());
            }
        }

        if ($enrolledAny) {
            app(StudentEvaluationService::class)->forget();
        }
    }

    private function create(Program $program, array $result, ImportBatch $batch): void
    {
        $f = $result['fields'];
        $password = Str::password(12);

        DB::transaction(function () use ($program, $f, $result, $password, $batch) {
            $user = User::create([
                'name' => trim("{$f['firstName']} {$f['lastName']}"),
                'email' => $f['email'],
                'password' => Hash::make($password),
                'role' => User::ROLE_STUDENT,
                'id_number' => $f['studentId'],
            ]);
            $user->markEmailAsVerified();

            $classSectionId = $result['isIrregular']
                ? null
                : $program->classSections()
                    ->firstOrCreate(['year_level' => $f['year'], 'block' => $f['block']])
                    ->id;

            $student = $program->students()->create([
                'student_number' => $f['studentId'],
                'email' => $f['email'],
                'first_name' => $f['firstName'],
                'middle_name' => $f['middleName'] ?: null,
                'last_name' => $f['lastName'],
                'suffix' => $f['suffix'] ?: null,
                'is_irregular' => $result['isIrregular'],
                'class_section_id' => $classSectionId,
                'user_id' => $user->id,
            ]);

            if ($result['isIrregular'] && $result['pivot'] !== []) {
                $student->enrolledSubjects()->sync($result['pivot']);
            }

            Mail::to($student->email)->queue(new StudentAccountCreated($student, $password));
            $batch->recordSuccess(count($result['subjectIds']), $result['isIrregular']);
        });
    }

    // add only, never removes or edits
    private function enroll(array $result, ImportBatch $batch): void
    {
        Student::findOrFail($result['existingStudentId'])
            ->enrolledSubjects()
            ->syncWithoutDetaching($result['pivot']);

        $batch->recordSuccess(count($result['subjectIds']));
    }
}
