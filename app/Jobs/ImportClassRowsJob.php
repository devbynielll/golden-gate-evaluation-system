<?php

namespace App\Jobs;

use App\Models\{Faculty, ImportBatch, Program};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\ActiveClassPeriod;

class ImportClassRowsJob
{
    use Dispatchable;

    public function __construct(private array $rows, private array $mapping, private int $batchId, private int $programId, private int $periodId) {}

    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        $records = collect($this->rows)->map(function ($row) {
            $get = fn ($field) => isset($this->mapping[$field]) ? trim((string) ($row[$this->mapping[$field]] ?? '')) : '';
            $faculty = $get('facultyId');

            return ['year' => $get('year'), 'block' => $get('block'), 'subjectCode' => $get('subjectCode'),
                'description' => $get('description'), 'facultyName' => $get('facultyName'),
                'facultyId' => $faculty === '' || Str::lower($faculty) === 'tba' ? null : $faculty];
        });
        $key = fn ($row) => json_encode([(int) $row['year'], Str::lower($row['block']), Str::lower($row['subjectCode'])]);
        $conflicts = $records->groupBy($key)->filter(fn ($group) => $group
            ->map(fn ($row) => $row['facultyId'] === null ? null : Str::lower($row['facultyId']))->unique()->count() > 1)->keys()->all();

        foreach ($records as $index => $data) {
            try {
                Validator::make($data, [
                    'year' => ['required', 'integer', 'min:1', 'max:6'], 'block' => ['required', 'string', 'max:10'],
                    'subjectCode' => ['required', 'string', 'max:20'], 'facultyId' => ['nullable', 'string', 'max:20'],
                    'description' => ['nullable', 'string', 'max:255'], 'facultyName' => ['nullable', 'string', 'max:255'],
                ])->validate();
                if (in_array($key($data), $conflicts, true)) {
                    throw ValidationException::withMessages(['facultyId' => 'Conflicting professors for this class/subject in the file; none of those rows were imported.']);
                }
                $result = DB::transaction(function () use ($data) {
                    // Serializes import-created sections; uses the same section/offering lock order as manual edits and submissions.
                    ActiveClassPeriod::lock($this->periodId);
                    $program = Program::whereKey($this->programId)->lockForUpdate()->firstOrFail();
                    $subject = $program->subjects()->whereRaw('LOWER(code) = ?', [Str::lower($data['subjectCode'])])->first();
                    if (! $subject) {
                        throw ValidationException::withMessages(['subjectCode' => 'Subject code does not exist in this program. Import or add the subject first.']);
                    }
                    // Faculty IDs are global to this institution. A class subject stays in
                    // the importing program, while an existing faculty member may teach it.
                    $faculty = $data['facultyId'] === null ? null : Faculty::whereRaw('LOWER(faculty_id) = ?', [Str::lower($data['facultyId'])])->first();
                    if ($data['facultyId'] !== null && ! $faculty) {
                        throw ValidationException::withMessages(['facultyId' => 'Faculty ID does not exist in this institution. Names are not faculty IDs.']);
                    }
                    $normalize = fn ($value) => Str::lower(Str::squish($value));
                    if ($data['description'] !== '' && $normalize($data['description']) !== $normalize($subject->description)) {
                        throw ValidationException::withMessages(['description' => 'Description does not match the existing subject code. Subject Code is authoritative; no subject was changed.']);
                    }
                    if ($data['facultyName'] !== '' && ! ($faculty === null && $normalize($data['facultyName']) === 'tba')
                        && ($faculty === null || $normalize($data['facultyName']) !== $normalize($faculty->name))) {
                        throw ValidationException::withMessages(['facultyName' => 'Faculty Name does not match Faculty ID. Map a valid existing Faculty ID; names are never used to guess a professor.']);
                    }
                    $section = $program->classSections()->where('year_level', (int) $data['year'])
                        ->whereRaw('LOWER(block) = ?', [Str::lower($data['block'])])->lockForUpdate()->first();
                    $section ??= $program->classSections()->create(['year_level' => (int) $data['year'], 'block' => $data['block']]);
                    $offering = $section->classSubjects()->where('review_period_id', $this->periodId)
                        ->where('subject_id', $subject->id)->lockForUpdate()->first();
                    if ($offering) {
                        if ($offering->faculty_id !== $faculty?->id) {
                            throw ValidationException::withMessages(['facultyId' => 'Offering already exists with a different professor or TBA. Import will not reassign it; review it using manual Edit.']);
                        }

                        return 'skip';
                    }
                    $section->classSubjects()->create(['subject_id' => $subject->id, 'faculty_id' => $faculty?->id, 'review_period_id' => $this->periodId]);

                    return 'create';
                });
                $result === 'skip' ? $batch->recordSkip($index + 1, 'Identical offering already exists; unchanged.') : $batch->recordSuccess();
            } catch (ValidationException $e) {
                $batch->recordFailure($index + 1, implode(' ', $e->validator->errors()->all()));
            } catch (\Throwable $e) {
                report($e);
                $batch->recordFailure($index + 1, 'This row could not be saved; its changes were rolled back. Check references or retry.');
            }
        }
    }
}
