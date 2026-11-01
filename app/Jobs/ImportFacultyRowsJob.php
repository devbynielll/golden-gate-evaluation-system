<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\Program;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;

class ImportFacultyRowsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $rows,
        private array $mapping,
        private int $firstRowNumber,
        private int $importBatchId,
        private int $programId,
    ) {}

    public function handle(): void
    {
        if ($this->batchId && $this->batch()?->cancelled()) {
            return;
        }

        $batch = ImportBatch::find($this->importBatchId);
        $program = Program::find($this->programId);
        if (! $batch || ! $program) {
            return;
        }

        foreach (array_values($this->rows) as $i => $row) {
            $rowNumber = $this->firstRowNumber + $i;

            try {
                \Illuminate\Support\Facades\DB::transaction(fn () => $this->importRow($program, $row, $batch));
            } catch (\Throwable $e) {
                $batch->recordFailure($rowNumber, $e->getMessage());
            }
        }
    }

    private function importRow(Program $program, array $row, ImportBatch $batch): void
    {
        $get = fn (string $field) => isset($this->mapping[$field])
            ? trim((string) ($row[$this->mapping[$field]] ?? ''))
            : null;

        $data = Validator::make([
            'faculty_id' => $get('facultyId'),
            'name' => $get('name'),
        ], [
            'faculty_id' => ['required', 'string', 'max:20', 'unique:faculty,faculty_id'],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $subjectCodes = array_filter(array_map('trim', explode(',', (string) $get('subjectCodes'))));
        $subjectIds = empty($subjectCodes)
            ? []
            : $program->subjects()->whereIn('code', $subjectCodes)->pluck('id')->all();

        $faculty = $program->faculty()->create([
            'faculty_id' => $data['faculty_id'],
            'name' => $data['name'],
        ]);
        $faculty->qualifiedSubjects()->sync($subjectIds);

        $batch->recordSuccess();
    }
}
