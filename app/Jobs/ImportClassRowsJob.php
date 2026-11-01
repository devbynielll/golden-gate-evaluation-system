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

    private bool $dryRun=false;
    private array $reviewRows=[];
    public function review(): array
    {
        $this->dryRun=true;$this->handle();
        $count=fn($action)=>count(array_filter($this->reviewRows,fn($row)=>$row['action']===$action));
        return ['summary'=>['create'=>$count('create'),'enroll'=>0,'skip'=>$count('skip'),'error'=>$count('error'),'subjectsToAdd'=>0],'rows'=>$this->reviewRows,'truncated'=>false];
    }
    public function handle(): void
    {
        $batch = $this->dryRun ? null : ImportBatch::findOrFail($this->batchId);
        $records = collect($this->rows)->map(function ($row) {
            $get = fn ($field) => isset($this->mapping[$field]) ? trim((string) ($row[$this->mapping[$field]] ?? '')) : '';
            $faculty = $get('facultyId');

            return ['year' => $get('year'), 'block' => $get('block'), 'subjectCode' => $get('subjectCode'),
                'description' => $get('description'), 'facultyName' => $get('facultyName'),
                'facultyId' => $faculty];
        });
        $expanded = [];
        foreach ($records as $index => $row) {
            try {
                $codes = \App\Support\PositionalClassValues::split($row['subjectCode']);
                $rawIds = ($row['facultyId'] ?? '') === '' ? (count($codes) === 1 ? [null] : []) : \App\Support\PositionalClassValues::split($row['facultyId']);
                $column = fn($field) => isset($this->mapping[$field]) ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->mapping[$field]+1) : 'not mapped';
                if (count($codes) !== count($rawIds)) throw new \DomainException('Unequal positional counts: Subject Code column '.$column('subjectCode').' = '.count($codes).'; Faculty ID column '.$column('facultyId').' = '.count($rawIds).'. Check the selected columns and comma separators; this is not proof the spreadsheet is invalid. Entire row rejected.');
                if (count($codes)>1 && in_array('', $rawIds, true)) throw new \DomainException('An empty Faculty ID position is ambiguous. Use an explicit TBA for an intentionally unassigned subject.');
                $ids = array_map(fn($id) => $id === null || $id === '' || Str::lower($id) === 'tba' ? null : $id, $rawIds);
                $expectedDescriptions = array_map(fn($code) => \App\Models\Subject::where('program_id',$this->programId)->whereRaw('LOWER(code) = ?',[Str::lower($code)])->value('description'), $codes);
                $expectedNames = array_map(fn($id) => $id === null ? 'TBA' : Faculty::whereRaw('LOWER(faculty_id) = ?',[Str::lower($id)])->value('name'), $ids);
                $descriptions = \App\Support\PositionalClassValues::labels($row['description'],$expectedDescriptions,'Description');
                $names = \App\Support\PositionalClassValues::labels($row['facultyName'],$expectedNames,'Faculty Name');
                foreach ($codes as $i => $code) $expanded[$index][] = array_replace($row,['subjectCode'=>$code,'facultyId'=>$ids[$i],'description'=>$descriptions[$i],'facultyName'=>$names[$i]]);
            } catch (\DomainException $e) { $expanded[$index] = $e->getMessage(); }
            catch (\Throwable $e) { report($e); $expanded[$index] = 'This row could not be validated. Check references or retry; no assignments were saved.'; }
        }
        $key = fn ($row) => json_encode([(int) $row['year'], Str::lower($row['block']), Str::lower($row['subjectCode'])]);
        $flat = collect($expanded)->filter(fn ($rows) => is_array($rows))->flatten(1);
        $conflicts = $flat->groupBy($key)->filter(fn ($group) => $group->map(fn ($row) => $row['facultyId'] === null ? null : Str::lower($row['facultyId']))->unique()->count()>1)->keys()->all();
        foreach ($expanded as $index => $rows) {
            try {
                if (is_string($rows)) throw new \DomainException($rows);
                $outcomes = DB::transaction(function () use ($rows,$conflicts,$key) {
                    $results=[];$errors=[];
                    foreach ($rows as $data) {
                        try { $results[]=$this->saveAssignment($data,$conflicts,$key); }
                        catch (ValidationException $e) { $message=$data['subjectCode'].': '.implode(' ', $e->validator->errors()->all());if (!$this->dryRun) throw new \DomainException($message);$errors[]=$message; }
                    }
                    if ($errors) throw new \DomainException(implode(' ', $errors));
                    return $results;
                });
                $action=in_array('create',$outcomes,true)?'create':'skip';
                if ($this->dryRun) $this->reviewRows[]=$this->reviewRow($index,$rows,$action,[]);
                else $action==='create' ? $batch->recordSuccess() : $batch->recordSkip($index+1,'All subject assignments already exist; unchanged.');
            } catch (\Throwable $e) {
                $message=$e instanceof \DomainException ? $e->getMessage() : 'Row rolled back; check all subject/faculty references.';
                if ($this->dryRun) $this->reviewRows[]=$this->reviewRow($index,is_array($rows)?$rows:[],'error',[$message,'Entire row rejected; no assignments will be saved.']);
                else $batch->recordFailure($index+1,$message);
            }
        }
    }

    private function reviewRow($index,array $rows,string $action,array $messages): array
    {
        return ['rows'=>[$index+1],'studentId'=>($rows[0]['year'] ?? '').' / '.($rows[0]['block'] ?? ''),'name'=>'','action'=>$action,
            'subjects'=>array_map(fn($row)=>$row['subjectCode'].' → '.($row['facultyId'] ?? 'TBA'),$rows),'messages'=>$messages,'warnings'=>[]];
    }
    private function saveAssignment(array $data, array $conflicts, callable $key): string
    {
        Validator::make($data, [
            'year' => ['required', 'integer', 'min:1', 'max:6'], 'block' => ['required', 'string', 'max:10'],
            'subjectCode' => ['required', 'string', 'max:20'], 'facultyId' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'], 'facultyName' => ['nullable', 'string', 'max:255'],
        ])->validate();
        if (in_array($key($data), $conflicts, true)) {
            throw ValidationException::withMessages(['facultyId' => 'Conflicting professors for this class/subject in the file; none of those rows were imported.']);
        }
        return DB::transaction(function () use ($data) {
            // Serializes import-created sections; uses the same section/offering lock order as manual edits and submissions.
            if (!$this->dryRun) ActiveClassPeriod::lock($this->periodId);
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
            if (!$section && $this->dryRun) return 'create';
            $section ??= $program->classSections()->create(['year_level' => (int) $data['year'], 'block' => $data['block']]);
            $offering = $section->classSubjects()->where('review_period_id', $this->periodId)
                ->where('subject_id', $subject->id)->lockForUpdate()->first();
            if ($offering) {
                if ($offering->faculty_id !== $faculty?->id) {
                    throw ValidationException::withMessages(['facultyId' => 'Offering already exists with a different professor or TBA. Import will not reassign it; review it using manual Edit.']);
                }

                return 'skip';
            }
            if ($this->dryRun) return 'create';
            $section->classSubjects()->create(['subject_id' => $subject->id, 'faculty_id' => $faculty?->id, 'review_period_id' => $this->periodId]);

            return 'create';
        });
    }
}
