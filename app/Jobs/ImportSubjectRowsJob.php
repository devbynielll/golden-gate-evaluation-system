<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\Program;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportSubjectRowsJob
{
    use Dispatchable;

    public function __construct(private array $rows, private array $mapping, private int $batchId, private int $programId) {}

    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        $records = collect($this->rows)->map(function ($row) {
            $get = fn ($field) => trim((string) ($row[$this->mapping[$field]] ?? ''));

            return ['code' => $get('code'), 'description' => $get('description'), 'year_level' => $get('year')];
        });
        $conflicts = $records->groupBy(fn ($row) => Str::lower($row['code']))
            ->filter(fn ($group) => $group->map(fn ($row) => [$row['description'], $row['year_level']])->unique()->count() > 1)->keys()->all();

        foreach ($records as $index => $data) {
            try {
                $data = Validator::make($data, [
                    'code' => ['required', 'string', 'max:20'], 'description' => ['required', 'string', 'max:255'],
                    'year_level' => ['required', 'integer', 'min:1', 'max:6'],
                ])->validate();
                if (in_array(Str::lower($data['code']), $conflicts, true)) {
                    throw ValidationException::withMessages(['code' => 'Conflicting rows for this subject code; none of those rows were imported.']);
                }
                $result = DB::transaction(function () use ($data) {
                    $program = Program::whereKey($this->programId)->lockForUpdate()->firstOrFail();
                    $existing = $program->subjects()->whereRaw('LOWER(code) = ?', [Str::lower($data['code'])])->lockForUpdate()->first();
                    if ($existing) {
                        if ($existing->description !== $data['description'] || (int) $existing->year_level !== (int) $data['year_level']) {
                            throw ValidationException::withMessages(['code' => 'Subject already exists with different details. Use manual Edit; imports never overwrite it.']);
                        }

                        return 'skip';
                    }
                    $program->subjects()->create($data);

                    return 'create';
                });
                $result === 'skip' ? $batch->recordSkip($index + 1, 'Identical subject already exists; unchanged.') : $batch->recordSuccess();
            } catch (ValidationException $e) {
                $batch->recordFailure($index + 1, implode(' ', $e->validator->errors()->all()));
            } catch (\Throwable $e) {
                report($e);
                $batch->recordFailure($index + 1, 'This row could not be saved; its changes were rolled back. Check references or retry.');
            }
        }
    }
}
