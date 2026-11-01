<?php

namespace Tests\Feature;

use App\Mail\StudentAccountCreated;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(), 'is_active' => true,
        ]);
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->department = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $this->department->programs()->create(['code' => 'BSIT', 'name' => 'IT']);

        foreach (['IT101', 'IT102', 'IT103'] as $code) {
            Subject::create(['program_id' => $this->program->id, 'code' => $code, 'description' => $code, 'year_level' => 1]);
        }
    }

    private function base(): array
    {
        return ['department' => $this->department, 'program' => $this->program];
    }

    private function upload(string $csv): string
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('programs.students.import.preview', $this->base()),
            ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)],
        )->assertOk();

        return $response->json('token');
    }

    // mapping by column order: A..J
    private function payload(string $token, bool $updateExisting = false, ?array $mapping = null): array
    {
        return [
            'token' => $token,
            'startRow' => 2,
            'updateExisting' => $updateExisting,
            'mapping' => $mapping ?? [
                'studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3,
                'year' => 4, 'block' => 5, 'isIrregular' => 6, 'subjectCodes' => 7,
            ],
        ];
    }

    private function validateRoute(): string
    {
        return route('programs.students.import.validate', $this->base());
    }

    private function storeRoute(): string
    {
        return route('programs.students.import.store', $this->base());
    }

    private function runImport(string $csv, bool $updateExisting = false, ?array $mapping = null): ImportBatch
    {
        $token = $this->upload($csv);
        $id = $this->actingAs($this->admin)
            ->postJson($this->storeRoute(), $this->payload($token, $updateExisting, $mapping))
            ->assertOk()->json('batchId');

        return ImportBatch::findOrFail($id);
    }

    private const HEADER = "id,email,first,last,year,block,irregular,subjects\n";

    public function test_student_only_import_still_works(): void
    {
        $batch = $this->runImport(self::HEADER."2024-0001,a@x.test,Ann,Lee,1,A,,\n2024-0002,b@x.test,Bob,Ray,2,B,N,\n");

        $this->assertSame(2, $batch->success_count);
        $this->assertSame(0, $batch->failure_count);
        $this->assertSame(2, Student::count());
        $this->assertSame(0, Student::first()->enrolledSubjects()->count());
        $this->assertNotNull(Student::first()->class_section_id);
        Mail::assertQueued(StudentAccountCreated::class, 2);
    }

    public function test_irregular_student_is_enrolled_only_in_listed_subjects(): void
    {
        $batch = $this->runImport(self::HEADER."2024-0003,c@x.test,Cy,Dee,,,Y,\"IT101, it102\"\n");

        $student = Student::where('student_number', '2024-0003')->firstOrFail();
        $this->assertTrue($student->is_irregular);
        $this->assertEqualsCanonicalizing(['IT101', 'IT102'], $student->enrolledSubjects->pluck('code')->all());
        $this->assertSame(2, $batch->enrollments_count);
    }

    public function test_one_subject_per_row_format_is_merged_per_student(): void
    {
        $batch = $this->runImport(self::HEADER
            ."2024-0004,d@x.test,Di,Fox,,,Y,IT101\n"
            ."2024-0004,,,,,,,IT102\n"
            ."2024-0004,,,,,,,IT101\n");

        $this->assertSame(1, $batch->total_rows);
        $this->assertSame(1, Student::count());
        $this->assertSame(2, Student::first()->enrolledSubjects()->count());
    }

    public function test_regular_student_subject_codes_are_not_enrolled(): void
    {
        $this->runImport(self::HEADER."2024-0005,e@x.test,Eve,Gray,1,A,N,\"IT101,IT102\"\n");

        $student = Student::firstOrFail();
        $this->assertFalse($student->is_irregular);
        $this->assertSame(0, $student->enrolledSubjects()->count());
    }

    public function test_unknown_subject_code_rejects_the_student_entirely(): void
    {
        $batch = $this->runImport(self::HEADER."2024-0006,f@x.test,Flo,Hart,,,Y,\"IT101,NOPE9\"\n");

        $this->assertSame(1, $batch->failure_count);
        $this->assertSame(0, Student::count());
        $this->assertSame(0, User::where('email', 'f@x.test')->count());
        $this->assertStringContainsString('NOPE9', $batch->errors[0]['message']);
    }

    public function test_existing_students_are_skipped_and_untouched_by_default(): void
    {
        $this->runImport(self::HEADER."2024-0007,g@x.test,Gus,Ivy,,,Y,IT101\n");
        $batch = $this->runImport(self::HEADER."2024-0007,g@x.test,CHANGED,Name,,,Y,IT102\n");

        $this->assertSame(1, $batch->skipped_count);
        $this->assertSame(0, $batch->failure_count);
        $student = Student::firstOrFail();
        $this->assertSame('Gus', $student->first_name);
        $this->assertSame(['IT101'], $student->enrolledSubjects->pluck('code')->all());
    }

    public function test_update_existing_adds_subjects_without_removing_or_editing(): void
    {
        $this->runImport(self::HEADER."2024-0008,h@x.test,Hal,Jay,,,Y,IT101\n");
        $batch = $this->runImport(self::HEADER."2024-0008,,CHANGED,,,,,\"IT101,IT103\"\n", updateExisting: true);

        $student = Student::firstOrFail();
        $this->assertSame('Hal', $student->first_name);
        $this->assertEqualsCanonicalizing(['IT101', 'IT103'], $student->enrolledSubjects->pluck('code')->all());
        $this->assertSame(1, $batch->success_count);
        $this->assertSame(1, $batch->enrollments_count);
        Mail::assertQueued(StudentAccountCreated::class, 1);
    }

    public function test_update_existing_never_enrolls_regular_students_or_mismatched_emails(): void
    {
        $this->runImport(self::HEADER."2024-0009,i@x.test,Ian,Kim,1,A,N,\n2024-0010,j@x.test,Jo,Lin,,,Y,IT101\n");

        $batch = $this->runImport(
            self::HEADER."2024-0009,,,,,,,IT101\n2024-0010,wrong@x.test,,,,,,IT102\n",
            updateExisting: true,
        );

        $this->assertSame(1, $batch->skipped_count);
        $this->assertSame(1, $batch->failure_count);
        $this->assertSame(0, Student::where('student_number', '2024-0009')->first()->enrolledSubjects()->count());
        $this->assertSame(['IT101'], Student::where('student_number', '2024-0010')->first()->enrolledSubjects->pluck('code')->all());
    }

    public function test_preview_reports_actions_without_saving_anything(): void
    {
        $this->runImport(self::HEADER."2024-0011,k@x.test,Kai,Moe,,,Y,IT101\n");
        $before = [Student::count(), User::count(), ImportBatch::count()];

        $csv = self::HEADER
            ."2024-0012,l@x.test,Lou,Ng,,,Y,\"IT101,IT101,IT102\"\n"   // new, duplicate code
            ."2024-0011,,,,,,,IT102\n"                                  // existing -> enroll
            ."2024-0013,m@x.test,Max,Orr,,,Y,BAD1\n"                    // unknown subject
            ."2024-0014,l@x.test,Dup,Email,1,A,N,\n"                    // email used earlier in file
            .",n@x.test,No,Id,1,A,N,\n"                                 // missing id
            ."2024-0015,o@x.test,Reg,Ular,,,N,\n";                      // regular w/o year/block

        $response = $this->actingAs($this->admin)
            ->postJson($this->validateRoute(), $this->payload($this->upload($csv), true))
            ->assertOk();

        $this->assertSame(
            ['students' => 6, 'create' => 1, 'enroll' => 1, 'skip' => 0, 'error' => 4, 'subjectsToAdd' => 3],
            $response->json('summary'),
        );
        $this->assertSame($before, [Student::count(), User::count(), ImportBatch::count()]);

        $byId = collect($response->json('rows'))->keyBy('studentId');
        $this->assertSame(['IT101', 'IT102'], $byId['2024-0012']['subjects']);
        $this->assertNotEmpty($byId['2024-0012']['warnings']);
        $this->assertStringContainsString('BAD1', $byId['2024-0013']['messages'][0]);
    }

    public function test_conflicting_repeated_rows_are_flagged(): void
    {
        $csv = self::HEADER."2024-0016,p@x.test,Pat,Quinn,,,Y,IT101\n2024-0016,other@x.test,,,,,,IT102\n";

        $this->actingAs($this->admin)
            ->postJson($this->validateRoute(), $this->payload($this->upload($csv)))
            ->assertOk()
            ->assertJsonPath('summary.error', 1);
    }

    public function test_student_id_is_the_only_required_mapping(): void
    {
        $token = $this->upload(self::HEADER."2024-0017,q@x.test,Quin,Rex,1,A,N,\n");

        $this->actingAs($this->admin)
            ->postJson($this->validateRoute(), $this->payload($token, false, ['email' => 1]))
            ->assertUnprocessable();
    }

    // IT101 is taught in block 1-A and 1-B; IT102 only in 1-A
    private function schedule(): array
    {
        $a = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $b = $this->program->classSections()->create(['year_level' => 1, 'block' => 'B']);
        $id = fn (string $code) => Subject::where('code', $code)->value('id');
        foreach ([[$a, 'IT101'], [$b, 'IT101'], [$a, 'IT102']] as [$section, $code]) {
            ClassSubject::create(['class_section_id' => $section->id, 'subject_id' => $id($code), 'review_period_id' => ReviewPeriod::activeId()]);
        }

        return ['A' => $a->id, 'B' => $b->id];
    }

    private function sectionOf(Student $student, string $code): ?int
    {
        return $student->enrolledSubjects()->where('code', $code)->first()?->pivot->class_section_id;
    }

    public function test_subject_taught_in_several_blocks_needs_a_block(): void
    {
        $this->schedule();
        $batch = $this->runImport(self::HEADER."2024-0020,r@x.test,Rae,Sol,,,Y,IT101\n");

        $this->assertSame(1, $batch->failure_count);
        $this->assertSame(0, Student::count());
        $this->assertStringContainsString('1-A, 1-B', $batch->errors[0]['message']);
    }

    public function test_block_can_be_given_inline_or_in_its_own_column(): void
    {
        $blocks = $this->schedule();
        $this->runImport(self::HEADER."2024-0021,s@x.test,Sam,Tan,,,Y,\"IT101:B, IT102\"\n");
        $student = Student::where('student_number', '2024-0021')->firstOrFail();
        $this->assertSame($blocks['B'], $this->sectionOf($student, 'IT101'));
        $this->assertSame($blocks['A'], $this->sectionOf($student, 'IT102')); // only one offering -> unambiguous

        $csv = "id,email,first,last,year,block,irregular,subjects,subjblock\n2024-0022,u@x.test,Uma,Vee,,,Y,IT101,1A\n";
        $this->runImport($csv, mapping: [
            'studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3, 'isIrregular' => 6, 'subjectCodes' => 7, 'subjectBlock' => 8,
        ]);
        $this->assertSame($blocks['A'], $this->sectionOf(Student::where('student_number', '2024-0022')->firstOrFail(), 'IT101'));
    }

    public function test_block_that_does_not_offer_the_subject_is_rejected(): void
    {
        $this->schedule();
        $batch = $this->runImport(self::HEADER."2024-0023,v@x.test,Val,Wu,,,Y,IT102:B\n");

        $this->assertSame(1, $batch->failure_count);
        $this->assertStringContainsString("isn't offered in block B", $batch->errors[0]['message']);
    }

    public function test_existing_enrollment_without_a_block_gets_it_filled_but_never_changed(): void
    {
        $blocks = $this->schedule();
        $this->runImport(self::HEADER."2024-0024,w@x.test,Wes,Xu,,,Y,IT102\n");
        $student = Student::firstOrFail();
        $student->enrolledSubjects()->attach(Subject::where('code', 'IT101')->value('id')); // legacy row: no block

        $this->runImport(self::HEADER."2024-0024,,,,,,,IT101:B\n", updateExisting: true);
        $this->assertSame($blocks['B'], $this->sectionOf($student, 'IT101'));

        // a later file naming a different block does not move an already-recorded one
        $batch = $this->runImport(self::HEADER."2024-0024,,,,,,,IT101:A\n", updateExisting: true);
        $this->assertSame($blocks['B'], $this->sectionOf($student, 'IT101'));
        $this->assertSame(1, $batch->skipped_count);
    }

    public function test_admin_form_requires_a_block_for_multi_block_subjects(): void
    {
        $blocks = $this->schedule();
        $it101 = Subject::where('code', 'IT101')->value('id');
        $form = fn (array $enrollments) => [
            'studentId' => '2024-0030', 'email' => 'y@x.test', 'firstName' => 'Yan', 'lastName' => 'Zed',
            'isIrregular' => true, 'enrollments' => $enrollments,
        ];
        $url = route('programs.students.store', $this->base());

        $this->actingAs($this->admin)->post($url, $form([['subjectId' => $it101, 'classSectionId' => null]]))
            ->assertSessionHasErrors('subjectIds');
        $this->assertSame(0, Student::count());

        $this->actingAs($this->admin)->post($url, $form([['subjectId' => $it101, 'classSectionId' => $blocks['B']]]));
        $this->assertSame($blocks['B'], $this->sectionOf(Student::firstOrFail(), 'IT101'));
    }
}
