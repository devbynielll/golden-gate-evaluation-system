<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\ImportBatch;
use App\Models\Office;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportOfficeRowsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $rows,
        private array $mapping,
        private int $firstRowNumber,
        private int $importBatchId,
    ) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->importBatchId);
        if (! $batch) {
            return;
        }

        foreach ($this->rows as $i => $row) {
            $rowNumber = $this->firstRowNumber + $i;

            try {
                $this->importRow($row, $batch);
            } catch (\Throwable $e) {
                $batch->recordFailure($rowNumber, $e->getMessage());
            }
        }
    }

    private function importRow(array $row, ImportBatch $batch): void
    {
        $get = fn (string $field) => isset($this->mapping[$field])
            ? trim((string) ($row[$this->mapping[$field]] ?? ''))
            : null;

        $departmentCode = $get('departmentCode');
        $normalize = fn ($value) => Str::lower(Str::squish((string) $value));
        $label = $normalize($departmentCode);
        $departmentId = null;
        if ($label !== '' && $label !== 'all departments') {
            $matches = Department::all(['id', 'code', 'name'])->filter(fn ($department) => in_array($label, [
                $normalize($department->code), $normalize($department->name),
                $normalize($department->code.' - '.$department->name),
            ], true));
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['departmentCode' => 'Department is unresolved or ambiguous. Use an existing code, full department name, CODE - Full Department Name, or All Departments.']);
            }
            $departmentId = $matches->first()->id;
        }

        $data = Validator::make([
            'code' => $get('code'),
            'name' => $get('name'),
        ], [
            'code' => ['required', 'string', 'max:20', 'unique:offices,code'],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        Office::create([
            'department_id' => $departmentId,
            'code' => $data['code'],
            'name' => $data['name'],
        ]);

        $batch->recordSuccess();
    }
}
