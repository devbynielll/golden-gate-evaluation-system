<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

// shared by the import preview and the import job so they always agree
class StudentImportAnalyzer
{
    public const ACTION_CREATE = 'create';

    public const ACTION_ENROLL = 'enroll';

    public const ACTION_SKIP = 'skip';

    public const ACTION_ERROR = 'error';

    private const NO_TERM = 'No active review period. Activate a term before importing subject enrollments.';

    private const FIELDS = ['studentId', 'email', 'firstName', 'middleName', 'lastName', 'suffix', 'year', 'block', 'isIrregular'];

    public function __construct(private EnrollmentSectionResolver $sections) {}

    // one record per student, rows of the same student are merged
    public function group(array $rows, array $mapping): array
    {
        $records = [];
        $emailOwner = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $get = fn (string $field) => isset($mapping[$field])
                ? trim((string) ($row[$mapping[$field]] ?? ''))
                : '';

            // skip blank rows
            if (implode('', array_map('trim', array_map('strval', $row))) === '') {
                continue;
            }

            $fields = [];
            foreach (self::FIELDS as $field) {
                $fields[$field] = $get($field);
            }
            $rowBlock = $get('subjectBlock');
            $codes = array_map(
                fn (array $c) => ['code' => $c['code'], 'block' => $c['block'] ?? ($rowBlock !== '' ? $rowBlock : null)],
                $this->splitCodes($get('subjectCodes')),
            );

            $key = $fields['studentId'] !== '' ? 'id:'.strtolower($fields['studentId']) : "row:{$rowNumber}";

            if (! isset($records[$key])) {
                $records[$key] = ['rows' => [$rowNumber], 'fields' => $fields, 'codes' => $codes, 'fileErrors' => []];

                continue;
            }

            $record = &$records[$key];
            $record['rows'][] = $rowNumber;
            $record['codes'] = array_merge($record['codes'], $codes);

            foreach ($fields as $name => $value) {
                if ($value === '') {
                    continue;
                }
                if ($record['fields'][$name] === '') {
                    $record['fields'][$name] = $value;
                } elseif (strcasecmp($record['fields'][$name], $value) !== 0) {
                    $record['fileErrors'][] = "Row {$rowNumber} repeats student {$fields['studentId']} with a different {$name}.";
                }
            }
            unset($record);
        }

        // same email on two students
        foreach ($records as $key => &$record) {
            $email = strtolower($record['fields']['email']);
            if ($email === '') {
                continue;
            }
            if (isset($emailOwner[$email]) && $emailOwner[$email] !== $key) {
                $record['fileErrors'][] = "Email {$record['fields']['email']} is already used by another student in this file.";
            } else {
                $emailOwner[$email] = $key;
            }
        }
        unset($record);

        return array_values($records);
    }

    // works out what happens to each record, writes nothing
    public function analyze(Program $program, array $records, bool $updateExisting, bool $regularOnly = false, bool $irregularOnly = false): array
    {
        $rosterOnly = $regularOnly || $irregularOnly;
        $subjectMap = $rosterOnly ? [] : $program->subjects()->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
        // enrollments need an active term
        $term = ReviewPeriod::where('is_active', true)->first();
        $offerings = $this->sections->offeringsFor(array_values($subjectMap), $term?->id);

        $numbers = array_filter(array_map(fn ($r) => $r['fields']['studentId'], $records));
        $emails = array_filter(array_map(fn ($r) => $r['fields']['email'], $records));
        $numbers = array_map('strtolower', $numbers);
        $emails = array_map('strtolower', $emails);

        $students = Student::query()->when(! $rosterOnly, fn ($query) => $query->with('enrolledSubjects:id,code'))->whereIn(DB::raw('LOWER(student_number)'), $numbers)->get()
            ->keyBy(fn ($s) => strtolower($s->student_number));
        $takenNumbers = User::whereIn(DB::raw('LOWER(id_number)'), $numbers)->pluck('id_number')->map('strtolower')->flip();
        $takenEmails = collect()
            ->merge(Student::whereIn(DB::raw('LOWER(email)'), $emails)->pluck('email'))
            ->merge(User::whereIn(DB::raw('LOWER(email)'), $emails)->pluck('email'))
            ->map('strtolower')->flip();

        return array_map(function (array $record) use ($program, $term, $updateExisting, $regularOnly, $irregularOnly, $rosterOnly, $subjectMap, $offerings, $students, $takenNumbers, $takenEmails) {
            $f = $record['fields'];
            $result = $record + ['action' => self::ACTION_ERROR, 'messages' => [], 'warnings' => [], 'subjectIds' => [], 'subjectCodes' => [], 'pivot' => [], 'alreadyEnrolled' => [], 'term' => $term?->label()];
            $result['messages'] = $record['fileErrors'];

            if ($f['studentId'] === '') {
                $result['messages'][] = 'Missing student ID.';
            }

            // dedupe codes, each must exist and resolve to one block
            $wanted = [];
            foreach ($record['codes'] as $entry) {
                $upper = strtoupper($entry['code']);
                if (isset($wanted[$upper])) {
                    if (($wanted[$upper]['block'] ?? null) !== null && $entry['block'] !== null
                        && strcasecmp($wanted[$upper]['block'], $entry['block']) !== 0) {
                        $result['messages'][] = "{$entry['code']} is listed with two different blocks.";

                        continue;
                    }
                    $result['warnings'][] = "Duplicate subject {$entry['code']} in file (counted once).";
                    $wanted[$upper]['block'] ??= $entry['block'];

                    continue;
                }
                $wanted[$upper] = $entry;
            }

            $unknown = [];
            $known = []; // upper code => [code, subjectId, sectionId|null]
            foreach ($wanted as $upper => $entry) {
                if (! isset($subjectMap[$upper])) {
                    $unknown[] = $entry['code'];

                    continue;
                }
                $subjectId = $subjectMap[$upper];
                [$sectionId, $error] = $this->sections->resolve($entry['code'], $offerings[$subjectId] ?? [], null, $entry['block']);
                if ($error) {
                    $result['messages'][] = $error;

                    continue;
                }
                $known[$upper] = ['code' => $entry['code'], 'subjectId' => $subjectId, 'sectionId' => $sectionId, 'offerings' => $offerings[$subjectId] ?? []];
            }
            if ($unknown) {
                $result['messages'][] = 'Unknown subject code(s) for this program: '.implode(', ', $unknown).'.';
            }

            $existing = $f['studentId'] !== '' ? $students->get(strtolower($f['studentId'])) : null;

            if ($existing) {
                return $this->analyzeExisting($result, $existing, $program, $rosterOnly ? false : $updateExisting, $known, $term !== null);
            }

            return $this->analyzeNew($result, $f, $known, $takenNumbers, $takenEmails, $term !== null, $regularOnly, $irregularOnly);
        }, $records);
    }

    private function label(array $entry): string
    {
        $block = collect($entry['offerings'])->firstWhere('sectionId', $entry['sectionId'])['label'] ?? null;

        return $block ? "{$entry['code']} ({$block})" : $entry['code'];
    }

    private function analyzeExisting(array $result, Student $existing, Program $program, bool $updateExisting, array $known, bool $hasTerm): array
    {
        $f = $result['fields'];

        if ($existing->program_id !== $program->id) {
            $result['messages'][] = "Student {$f['studentId']} already exists in a different program.";
        }
        if ($f['email'] !== '' && strcasecmp($f['email'], $existing->email) !== 0) {
            $result['messages'][] = "Email doesn't match the existing student {$f['studentId']} ({$existing->email}).";
        }
        if ($result['messages']) {
            return $result;
        }

        // existing students: only add subjects
        if (! $updateExisting) {
            $result['action'] = self::ACTION_SKIP;
            $result['messages'][] = 'Student already exists; left unchanged.';

            return $result;
        }
        if (! $existing->is_irregular) {
            $result['action'] = self::ACTION_SKIP;
            $result['messages'][] = 'Existing student is regular; subjects come from their block, so nothing was enrolled.';

            return $result;
        }
        if (! $known) {
            $result['action'] = self::ACTION_SKIP;
            $result['messages'][] = 'No subject codes given; nothing to add.';

            return $result;
        }
        if (! $hasTerm) {
            $result['messages'][] = self::NO_TERM;

            return $result;
        }

        $enrolled = $existing->enrolledSubjects->mapWithKeys(fn ($s) => [strtoupper($s->code) => $s->pivot->class_section_id]);
        $filled = [];
        foreach ($known as $upper => $entry) {
            if (! $enrolled->has($upper)) {
                $result['subjectIds'][] = $entry['subjectId'];
                $result['subjectCodes'][] = $this->label($entry);
                $result['pivot'][$entry['subjectId']] = ['class_section_id' => $entry['sectionId']];

                continue;
            }

            $current = $enrolled[$upper];
            if ($current === null && $entry['sectionId'] !== null) {
                // fill a missing block on old rows
                $result['pivot'][$entry['subjectId']] = ['class_section_id' => $entry['sectionId']];
                $filled[] = $this->label($entry);
            } elseif ($current !== null && $entry['sectionId'] !== null && $current !== $entry['sectionId']) {
                $result['warnings'][] = "{$entry['code']}: already enrolled in a different block; left unchanged.";
            } else {
                $result['alreadyEnrolled'][] = $entry['code'];
            }
        }

        if (! $result['pivot']) {
            $result['action'] = self::ACTION_SKIP;
            $result['messages'][] = $result['warnings'] ? implode(' ', $result['warnings']) : 'Already enrolled in all listed subjects.';
            $result['warnings'] = [];

            return $result;
        }
        if ($filled) {
            $result['warnings'][] = 'Block set on existing enrollment: '.implode(', ', $filled).'.';
        }
        if ($result['alreadyEnrolled']) {
            $result['warnings'][] = 'Already enrolled (skipped): '.implode(', ', $result['alreadyEnrolled']).'.';
        }

        $result['action'] = self::ACTION_ENROLL;
        $result['existingStudentId'] = $existing->id;

        return $result;
    }

    private function analyzeNew(array $result, array $f, array $known, $takenNumbers, $takenEmails, bool $hasTerm, bool $regularOnly = false, bool $irregularOnly = false): array
    {
        $isIrregular = in_array(strtolower($f['isIrregular']), ['1', 'y', 'yes', 'true'], true);
        if ($irregularOnly && ! $isIrregular) {
            $result['messages'][] = 'Irregular Import requires IRREGULAR (Y/N) = Y. Use the Regular tab for N rows.';
        }

        if ($regularOnly && ! in_array(strtolower($f['isIrregular']), ['', '0', 'n', 'no', 'false', '1', 'y', 'yes', 'true'], true)) {
            $result['messages'][] = 'Irregular? must be Y/N (or blank for regular).';
        }

        $validator = Validator::make([
            'student_number' => $f['studentId'],
            'email' => $f['email'],
            'first_name' => $f['firstName'],
            'middle_name' => $f['middleName'] ?: null,
            'last_name' => $f['lastName'],
            'suffix' => $f['suffix'] ?: null,
            'year' => $f['year'] ?: null,
            'block' => $f['block'] ?: null,
        ], [
            'student_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'block' => ['nullable', 'string', 'max:10'],
        ]);
        foreach ($validator->errors()->messages() as $field => $messages) {
            // blank id already reported
            if ($field !== 'student_number' || $f['studentId'] !== '') {
                array_push($result['messages'], ...$messages);
            }
        }

        if ($takenNumbers->has(strtolower($f['studentId']))) {
            $result['messages'][] = "Student ID {$f['studentId']} is already used by another account.";
        }
        if ($f['email'] !== '' && $takenEmails->has(strtolower($f['email']))) {
            $result['messages'][] = "Email {$f['email']} is already in use.";
        }

        if ($isIrregular) {
            if ($regularOnly) {
                $result['messages'][] = 'This Student Import creates regular students only. Use Add Student, select Irregular, and choose the attended courses and blocks in the manual enrollment form.';
            } elseif ($irregularOnly) {
                $result['warnings'][] = 'No subjects will be assigned. Open Edit Student after import to select the attended subjects and class sections.';
            } elseif (! $result['codes']) {
                $result['messages'][] = 'Irregular student has no subject codes.';
            } elseif (! $hasTerm) {
                $result['messages'][] = self::NO_TERM;
            }
        } else {
            if ($f['year'] === '' || $f['block'] === '') {
                $result['messages'][] = 'Regular student is missing year/block.';
            }
            if ($result['codes']) {
                $result['warnings'][] = 'Regular student: subject codes ignored (subjects come from their block).';
                // codes are ignored, so drop block errors
                $result['messages'] = array_values(array_filter(
                    $result['messages'],
                    fn ($m) => ! preg_match('/offered in|ambiguous|different blocks|no class scheduled/', $m),
                ));
            }
        }

        if ($result['messages']) {
            return $result;
        }

        $result['action'] = self::ACTION_CREATE;
        $result['isIrregular'] = $isIrregular;
        if ($isIrregular) {
            foreach ($known as $entry) {
                $result['subjectIds'][] = $entry['subjectId'];
                $result['subjectCodes'][] = $this->label($entry);
                $result['pivot'][$entry['subjectId']] = ['class_section_id' => $entry['sectionId']];
            }
        }

        return $result;
    }

    // "IT101", "IT101:A", "IT101:1A" -> [code, block|null]
    private function splitCodes(string $cell): array
    {
        $entries = [];
        foreach (preg_split('/[,;\r\n]+/', $cell) ?: [] as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            [$code, $block] = array_pad(array_map('trim', explode(':', $token, 2)), 2, null);
            $entries[] = ['code' => $code, 'block' => $block !== '' ? $block : null];
        }

        return $entries;
    }
}
