<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, Department, ImportBatch, ReviewPeriod, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Mail, Storage};
use Tests\TestCase;

// Synthetic verification of the existing import chain; no new import policy.
class ImportChainIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private $department;
    private $program;
    private $period;

    protected function setUp(): void
    {
        parent::setUp(); Cache::flush(); Storage::fake('local'); Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'department_admin']));
        $this->department = Department::create(['code' => 'CHAIN', 'name' => 'Synthetic import chain']);
        $this->program = $this->department->programs()->create(['code' => 'CHAIN', 'name' => 'Synthetic']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    }

    private function runImport(string $type, string $csv, array $mapping): ImportBatch
    {
        $base = [$this->department, $this->program];
        $extra = $type === 'classes' ? ['termId' => $this->period->id] : ($type === 'students' ? ['mode' => 'regular'] : []);
        $token = $this->postJson(route('programs.'.$type.'.import.preview', $base), $extra + ['file' => UploadedFile::fake()->createWithContent($type.'.csv', $csv)])->assertOk()->json('token');
        $id = $this->postJson(route('programs.'.$type.'.import.store', $base), $extra + ['token' => $token, 'mapping' => $mapping, 'startRow' => 2])->assertOk()->json('batchId');
        return ImportBatch::findOrFail($id);
    }

    private function subjects(): ImportBatch
    {
        return $this->runImport('subjects', "code,description,year\nS1,Synthetic One,1\nS2,Synthetic Two,1\n", ['code' => 0, 'description' => 1, 'year' => 2]);
    }

    private function faculty(): ImportBatch
    {
        return $this->runImport('faculty', "id,name\nF1,Synthetic Professor One\nF2,Synthetic Professor Two\n", ['facultyId' => 0, 'name' => 1]);
    }

    private function classes(string $rows = "1,A,S1,F1\n1,A,S2,F2\n1,B,S1,F2\n"): ImportBatch
    {
        return $this->runImport('classes', "year,block,subject,faculty\n".$rows, ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3]);
    }

    private function students(string $rows = "C-A,chain-a@example.invalid,Synthetic,A,1,A,N\nC-B,chain-b@example.invalid,Synthetic,B,1,B,N\n"): ImportBatch
    {
        return $this->runImport('students', "id,email,first,last,year,block,irregular\n".$rows, ['studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3, 'year' => 4, 'block' => 5, 'isIrregular' => 6]);
    }

    private function snapshot(): array
    {
        return collect(['subjects', 'faculty', 'class_sections', 'class_subjects', 'students', 'student_subject_enrollments', 'users'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_complete_import_chain_links_regular_students_to_correct_subjects_and_professors_without_overwrites(): void
    {
        $this->assertSame(2, $this->subjects()->success_count);
        $this->assertSame(2, $this->faculty()->success_count);
        $this->assertSame(3, $this->classes()->success_count);
        $this->assertSame(2, $this->students()->success_count);
        foreach (['C-A' => ['S1:F1', 'S2:F2'], 'C-B' => ['S1:F2']] as $number => $expected) {
            $student = Student::where('student_number', $number)->firstOrFail();
            $this->assertSame($this->program->id, $student->program_id);
            $this->assertSame(1, $student->classSection->year_level);
            $this->assertSame(substr($number, -1), $student->classSection->block);
            $this->assertEqualsCanonicalizing($expected, $student->offerings()->with(['subject', 'faculty'])->get()->map(fn ($offering) => $offering->subject->code.':'.$offering->faculty->faculty_id)->all());
            $this->assertSame(0, $student->enrolledSubjects()->count());
        }
        $before = $this->snapshot();
        $this->assertSame(2, $this->subjects()->skipped_count);
        $this->assertSame(2, $this->faculty()->failure_count); // Existing faculty importer rejects duplicate IDs rather than skipping them.
        $this->assertSame(3, $this->classes()->skipped_count);
        $this->assertSame(2, $this->students()->skipped_count);
        $this->assertSame($before, $this->snapshot());
        Mail::assertQueued(\App\Mail\StudentAccountCreated::class, 2);
    }

    public function test_missing_or_cross_program_references_never_create_incorrect_offerings_or_enrollments(): void
    {
        $this->subjects(); $this->faculty(); $this->classes(); $this->students();
        $other = $this->department->programs()->create(['code' => 'OTHER', 'name' => 'Other']);
        $other->faculty()->create(['faculty_id' => 'FOREIGN', 'name' => 'Foreign professor']);
        $other->subjects()->create(['code' => 'FOREIGN', 'description' => 'Foreign subject', 'year_level' => 1]);
        $before = $this->snapshot();
        $this->assertSame(3, $this->classes("1,Z,MISSING,F1\n1,Z,S1,MISSING\n1,Z,FOREIGN,F1\n")->failure_count);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, $this->classes("1,A,S1,F2\n")->failure_count); // Existing assignment is not replaced.
        $this->assertSame($before, $this->snapshot());
        $original = Student::where('student_number', 'C-A')->firstOrFail();
        $this->program = $other;
        $this->assertSame(1, $this->students("C-A,chain-a@example.invalid,Synthetic,A,1,A,N\n")->failure_count);
        $this->assertSame($original->program_id, $original->fresh()->program_id);
    }

    public function test_preexisting_missing_block_policy_creates_empty_section_without_inventing_eligibility(): void
    {
        $this->subjects(); $this->faculty(); $this->classes();
        $this->assertSame(1, $this->students("C-Z,chain-z@example.invalid,Synthetic,Z,1,Z,N\n")->success_count);
        $student = Student::where('student_number', 'C-Z')->firstOrFail();
        $this->assertSame('Z', $student->classSection->block);
        $this->assertSame(0, $student->offerings()->count());
        $this->assertSame(0, $student->enrolledSubjects()->count());
        // Recorded limitation: regular import auto-creates absent sections; it does not require a prior Class import.
    }
}
