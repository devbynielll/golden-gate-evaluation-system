<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, ImportBatch, Program, ReviewPeriod, Student, Subject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Tests\TestCase;

class StudentImportSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Program $program;
    private ReviewPeriod $period;
    private array $mapping = ['studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3, 'year' => 4, 'block' => 5, 'isIrregular' => 6];
    private const HEADER = "id,email,first,last,year,block,irregular\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'department_admin']));
        $this->department = Department::create(['code' => 'DEMO', 'name' => 'Demo']);
        $this->program = $this->department->programs()->create(['code' => 'IT', 'name' => 'IT']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    }

    private function url(string $action): string
    {
        return route('programs.students.import.'.$action, [$this->department, $this->program]);
    }

    private function upload(string $csv, bool $simplified = true): string
    {
        return $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)] + ($simplified ? ['mode' => 'regular'] : []))->assertOk()->json('token');
    }

    private function payload(string $token): array
    {
        return ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2, 'mode' => 'regular'];
    }

    private function execute(string $rows): ImportBatch
    {
        $token = $this->upload(self::HEADER.$rows);
        $id = $this->postJson($this->url('store'), $this->payload($token))->assertOk()->json('batchId');

        return ImportBatch::findOrFail($id);
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_simplified_regular_rows_keep_year_block_and_inherit_only_their_section_offerings(): void
    {
        $section = $this->program->classSections()->create(['year_level' => 2, 'block' => 'B']);
        $subject = $this->program->subjects()->create(['code' => 'IT201', 'description' => 'Demo', 'year_level' => 2]);
        $offering = $section->classSubjects()->create(['subject_id' => $subject->id, 'review_period_id' => $this->period->id]);
        $batch = $this->execute("NEW1,new1@example.test,Demo,One,2,B,N\nNEW2,new2@example.test,Demo,Two,1,A,\n");
        $this->assertSame(2, $batch->success_count);
        $student = Student::where('student_number', 'NEW1')->firstOrFail();
        $this->assertFalse($student->is_irregular);
        $this->assertSame($section->id, $student->class_section_id);
        $this->assertSame([$offering->id], $student->offerings()->pluck('id')->all());
        $this->assertDatabaseCount('student_subject_enrollments', 0);
        $this->assertSame(0, $batch->enrollments_count);
    }

    public function test_irregular_and_invalid_flags_report_manual_fallback_without_creating_accounts(): void
    {
        $token = $this->upload(self::HEADER."IRR,irregular@example.test,Demo,Irregular,,,Y\nBAD,bad@example.test,Demo,Bad,1,A,perhaps\n");
        $response = $this->postJson($this->url('validate'), $this->payload($token))->assertOk()->assertJsonPath('summary.error', 2);
        $this->assertStringContainsString('Use Add Student', $response->json('rows.0.messages.0'));
        $id = $this->postJson($this->url('store'), $this->payload($token))->assertOk()->json('batchId');
        $batch = ImportBatch::findOrFail($id);
        $this->assertSame(2, $batch->failure_count);
        $this->assertDatabaseCount('students', 0);
        $this->assertSame(0, User::where('role', 'student')->count());
        $this->assertDatabaseCount('student_subject_enrollments', 0);
    }

    public function test_existing_regular_and_irregular_accounts_sections_and_history_are_unchanged(): void
    {
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = $this->program->subjects()->create(['code' => 'KEEP', 'description' => 'Keep', 'year_level' => 1]);
        $faculty = $this->program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Demo']);
        $offering = $section->classSubjects()->create(['subject_id' => $subject->id, 'faculty_id' => $faculty->id, 'review_period_id' => $this->period->id]);
        $historical = ReviewPeriod::create(['academic_year' => '2025-2026', 'semester' => '1st Semester', 'start_date' => '2025-08-01', 'end_date' => '2026-01-31']);
        foreach ([false => 'REG', true => 'IRR'] as $irregular => $id) {
            $user = User::factory()->create(['id_number' => $id, 'email' => strtolower($id).'@example.test', 'role' => 'student']);
            $student = $this->program->students()->create(['user_id' => $user->id, 'student_number' => $id, 'email' => $user->email, 'first_name' => 'Keep', 'last_name' => 'Me', 'class_section_id' => $irregular ? null : $section->id, 'is_irregular' => (bool) $irregular]);
            if ($irregular) {
                foreach ([$this->period->id, $historical->id] as $period) $student->enrolledSubjects($period)->attach($subject->id, ['class_section_id' => $section->id]);
                $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id, 'class_subject_id' => $offering->id, 'evaluatee_type' => 'faculty', 'submitted_at' => now()]);
                $cat = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Demo']);
                $q = $cat->questions()->create(['text' => 'Demo?', 'order' => 1]);
                $evaluation->answers()->create(['criteria_question_id' => $q->id, 'rating' => 4]);
            }
        }
        $tables = ['users', 'students', 'class_sections', 'class_subjects', 'student_subject_enrollments', 'evaluations', 'evaluation_answers'];
        $before = $this->snapshot($tables);
        $batch = $this->execute("REG,,CHANGED,Name,4,Z,Y\nIRR,,CHANGED,Name,4,Z,N\n");
        $this->assertSame(2, $batch->skipped_count);
        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_removed_mappings_and_update_flag_are_rejected_for_both_review_and_import(): void
    {
        $token = $this->upload(self::HEADER."NEW,new@example.test,Demo,New,1,A,N\n");
        foreach (['validate', 'store'] as $action) {
            foreach ([['mapping' => $this->mapping + ['subjectCodes' => 7]], ['mapping' => $this->mapping + ['subjectBlock' => 8]], ['updateExisting' => true]] as $extra) {
                $this->postJson($this->url($action), array_replace($this->payload($token), $extra))->assertUnprocessable();
            }
        }
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_repeating_regular_file_skips_existing_and_grouped_duplicates_create_once(): void
    {
        $rows = "S1,s1@example.test,Demo,One,1,A,N\nS1,s1@example.test,Demo,One,1,A,N\n";
        $one = $this->execute($rows);
        $before = $this->snapshot(['users', 'students', 'class_sections']);
        $two = $this->execute($rows);
        $this->assertSame(1, $one->success_count);
        $this->assertSame(1, $two->skipped_count);
        $this->assertSame($before, $this->snapshot(['users', 'students', 'class_sections']));
        $this->assertDatabaseCount('students', 1);
    }

    public function test_missing_year_block_bad_email_and_conflicting_repeated_rows_are_errors(): void
    {
        $b = $this->execute("YEAR,year@example.test,Demo,One,,A,N\nBLOCK,block@example.test,Demo,Two,1,,N\nEMAIL,bad-email,Demo,Three,1,A,N\nDUP,d@example.test,Demo,Four,1,A,N\nDUP,d@example.test,Changed,Four,1,A,N\nOK,ok@example.test,Demo,Okay,1,A,N\n");
        $this->assertSame(4, $b->failure_count);
        $this->assertSame(1, $b->success_count);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_review_is_read_only_and_has_no_subject_enrollment_term_notice(): void
    {
        $token = $this->upload(self::HEADER."S,s@example.test,Demo,Student,1,A,N\n");
        $before = $this->snapshot(['users', 'students', 'class_sections', 'import_batches']);
        $res = $this->postJson($this->url('validate'), $this->payload($token))->assertOk()->assertJsonPath('summary.create', 1)->assertJsonPath('summary.enroll', 0)->assertJsonPath('summary.subjectsToAdd', 0);
        $this->assertArrayNotHasKey('term', $res->json());
        $this->assertSame($before, $this->snapshot(['users', 'students', 'class_sections', 'import_batches']));
    }

    public function test_regular_import_and_existing_skip_work_without_an_active_period(): void
    {
        $this->execute("OLD,old@example.test,Demo,Old,1,A,N\n");
        $this->period->update(['is_active' => false]);
        $b = $this->execute("OLD,,,,,,,\nNEW,new@example.test,Demo,New,1,A,N\n");
        $this->assertSame(1, $b->skipped_count);
        $this->assertSame(1, $b->success_count);
        $this->assertDatabaseCount('student_subject_enrollments', 0);
    }

    public function test_simplified_upload_cannot_be_downgraded_or_used_by_another_program_or_admin(): void
    {
        $token = $this->upload(self::HEADER."S,s@example.test,Demo,Student,1,A,N\n");
        $payload = $this->payload($token);
        $legacy = $payload;unset($legacy['mode']);
        $this->postJson($this->url('store'), $legacy)->assertUnprocessable();
        $other = $this->department->programs()->create(['code' => 'OTHER', 'name' => 'Other']);
        $this->postJson(route('programs.students.import.store', [$this->department, $other]), $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->postJson($this->url('store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_student_and_office_admin_are_denied_and_mismatched_department_is_rejected(): void
    {
        $foreign = Department::create(['code' => 'X', 'name' => 'Other']);
        $this->postJson(route('programs.students.import.preview', [$foreign, $this->program]))->assertNotFound();
        foreach (['student', 'office_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['preview', 'validate', 'store'] as $action) $this->postJson($this->url($action))->assertForbidden();
        }
    }

    public function test_explicit_legacy_irregular_api_remains_available_without_simplified_mode(): void
    {
        $subject = $this->program->subjects()->create(['code' => 'LEGACY', 'description' => 'Legacy', 'year_level' => 1]);
        $token = $this->upload("id,email,first,last,irregular,subjects\nLEG,l@example.test,Demo,Legacy,Y,LEGACY\n", false);
        $res = $this->postJson($this->url('store'), ['token' => $token, 'mapping' => ['studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3, 'isIrregular' => 4, 'subjectCodes' => 5], 'startRow' => 2])->assertOk();
        $student = Student::where('student_number', 'LEG')->firstOrFail();
        $this->assertTrue($student->is_irregular);
        $this->assertSame([$subject->id], $student->enrolledSubjects()->pluck('subjects.id')->all());
        $this->assertSame(1, ImportBatch::find($res->json('batchId'))->success_count);
    }

    public function test_manual_irregular_create_and_enrollment_workflow_remains_supported(): void
    {
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = $this->program->subjects()->create(['code' => 'MANUAL', 'description' => 'Manual', 'year_level' => 1]);
        $section->classSubjects()->create(['subject_id' => $subject->id, 'review_period_id' => $this->period->id]);
        $this->post(route('programs.students.store', [$this->department, $this->program]), ['studentId' => 'MAN', 'email' => 'manual@example.test', 'firstName' => 'Demo', 'lastName' => 'Manual', 'isIrregular' => true, 'enrollments' => [['subjectId' => $subject->id, 'classSectionId' => $section->id]]])->assertRedirect()->assertSessionHasNoErrors();
        $student = Student::where('student_number', 'MAN')->firstOrFail();
        $this->assertNull($student->class_section_id);
        $this->assertSame($section->id, $student->enrolledSubjects()->first()->pivot->class_section_id);
    }
}
