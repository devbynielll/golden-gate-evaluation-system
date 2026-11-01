<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, ImportBatch, Program, ReviewPeriod, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Tests\TestCase;

class ClassImportTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Program $program;
    private ReviewPeriod $period;
    private array $mapping = ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'department_admin']));
        $this->department = Department::create(['code' => 'CS', 'name' => 'Studies']);
        $this->program = $this->department->programs()->create(['code' => 'IT', 'name' => 'IT']);
        $this->period = $this->term('1st Semester', true);
        foreach (['IT101', 'IT102', 'IT103'] as $code) $this->program->subjects()->create(['code' => $code, 'description' => $code, 'year_level' => 1]);
        foreach (['F1', 'F2'] as $id) $this->program->faculty()->create(['faculty_id' => $id, 'name' => $id]);
    }

    private function term(string $semester, bool $active = false): ReviewPeriod
    {
        return ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => $semester, 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => $active]);
    }

    private function url(string $action): string
    {
        return route('programs.classes.import.'.$action, [$this->department, $this->program]);
    }

    private function upload(string $csv): string
    {
        return $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent('classes.csv', $csv), 'termId' => $this->period->id])->assertOk()->json('token');
    }

    private function execute(string $csv, ?array $mapping = null): ImportBatch
    {
        $id = $this->postJson($this->url('store'), ['token' => $this->upload($csv), 'mapping' => $mapping ?? $this->mapping, 'startRow' => 2, 'termId' => $this->period->id])->assertOk()->json('batchId');

        return ImportBatch::findOrFail($id);
    }

    private function csv(string $rows): string
    {
        return "year,block,subject,faculty\n".$rows;
    }

    public function test_mixed_reused_and_tba_professors_import_across_sections_without_qualifications(): void
    {
        $b = $this->execute($this->csv("1,A,IT101,F1\n1,A,IT102,F2\n1,A,IT103,F1\n1,B,IT101,F1\n1,B,IT102,TBA\n1,B,IT103,\n"));
        $this->assertSame(6, $b->success_count);
        $this->assertSame('completed', $b->status);
        $this->assertDatabaseCount('class_sections', 2);
        $this->assertDatabaseCount('class_subjects', 6);
        $this->assertSame(3, ClassSubject::where('faculty_id', $this->program->faculty()->where('faculty_id', 'F1')->value('id'))->count());
        $this->assertSame(2, ClassSubject::whereNull('faculty_id')->count());
        $this->assertDatabaseCount('faculty_subject', 0);
    }

    public function test_duplicates_repeated_imports_and_case_variants_are_safe(): void
    {
        $csv = $this->csv("1,A,IT101,F1\n1,a,it101,f1\n1,A,IT102,F2\n");
        $b = $this->execute($csv);
        $this->assertSame(2, $b->success_count);
        $this->assertSame(1, $b->skipped_count);
        $before = DB::table('class_subjects')->orderBy('id')->get()->toJson();
        $b = $this->execute($csv);
        $this->assertSame(3, $b->skipped_count);
        $this->assertSame($before, DB::table('class_subjects')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('class_sections', 1);
    }

    public function test_conflicting_rows_reject_every_assignment_for_that_key_before_any_writes(): void
    {
        $b = $this->execute($this->csv("1,A,IT101,F1\n1,A,IT101,F2\n1,B,IT102,F1\n1,B,IT102,TBA\n1,C,IT103,F1\n"));
        $this->assertSame(4, $b->failure_count);
        $this->assertSame([2, 3, 4, 5], array_column($b->errors, 'row'));
        $this->assertSame(1, $b->success_count);
        $this->assertDatabaseCount('class_sections', 1);
        $this->assertSame('C', $this->program->classSections()->first()->block);
    }

    public function test_invalid_references_do_not_create_empty_sections_while_existing_cross_program_faculty_is_allowed(): void
    {
        $foreign = $this->department->programs()->create(['code' => 'X', 'name' => 'Other']);
        $foreign->subjects()->create(['code' => 'X1', 'description' => 'Foreign', 'year_level' => 1]);
        $foreign->faculty()->create(['faculty_id' => 'XF', 'name' => 'Foreign']);
        $b = $this->execute($this->csv("1,A,X1,F1\n1,B,IT101,XF\n1,C,IT101,Missing\n1,D,,F1\n7,E,IT101,F1\n1,,IT101,F1\n1,F,Unknown,F1\n1,G,IT102,F2\n"));
        $this->assertSame(6, $b->failure_count);
        $this->assertSame(2, $b->success_count);
        $this->assertDatabaseCount('class_sections', 2);
        $this->assertDatabaseHas('class_subjects', ['faculty_id' => $foreign->faculty()->where('faculty_id', 'XF')->value('id')]);
    }

    public function test_evaluated_ids_assignments_answers_and_enrollments_survive_conflicts_and_additions(): void
    {
        $this->execute($this->csv("1,A,IT101,F1\n"));
        $offering = ClassSubject::firstOrFail();
        $student = $this->program->students()->create(['student_number' => 'S1', 'email' => 's@example.invalid', 'first_name' => 'Synthetic', 'last_name' => 'Student', 'class_section_id' => $offering->class_section_id, 'is_irregular' => true]);
        $student->enrolledSubjects($this->period->id)->attach($offering->subject_id, ['class_section_id' => $offering->class_section_id]);
        $cat = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Demo']);
        $q = $cat->questions()->create(['text' => 'Demo?', 'order' => 1]);
        $e = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id, 'evaluatee_type' => 'faculty', 'class_subject_id' => $offering->id, 'submitted_at' => now()]);
        $e->answers()->create(['criteria_question_id' => $q->id, 'rating' => 4]);
        $snap = [];
        foreach (['evaluations', 'evaluation_answers', 'student_subject_enrollments', 'students', 'class_subjects'] as $table) $snap[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $b = $this->execute($this->csv("1,A,IT101,F2\n1,A,IT102,F2\n"));
        $this->assertSame(1, $b->failure_count);
        $this->assertSame(1, $b->success_count);
        foreach (['evaluations', 'evaluation_answers', 'student_subject_enrollments', 'students'] as $table) $this->assertSame($snap[$table], DB::table($table)->orderBy('id')->get()->toJson());
        $this->assertSame($snap['class_subjects'], DB::table('class_subjects')->where('id', $offering->id)->get()->toJson());
        $b = $this->execute($this->csv("1,A,IT101,F1\n"));
        $this->assertSame(1, $b->skipped_count);
    }

    public function test_existing_unevaluated_tba_and_professor_are_never_silently_replaced_or_removed(): void
    {
        $this->execute($this->csv("1,A,IT101,\n1,A,IT102,F1\n"));
        $before = DB::table('class_subjects')->get()->toJson();
        $b = $this->execute($this->csv("1,A,IT101,F1\n1,A,IT102,\n"));
        $this->assertSame(2, $b->failure_count);
        $this->assertSame($before, DB::table('class_subjects')->get()->toJson());
    }

    public function test_selected_period_is_explicit_bound_to_upload_and_preserves_other_terms(): void
    {
        $this->execute($this->csv("1,A,IT101,F1\n"));
        $old = ClassSubject::first()->fresh()->toArray();
        $other = $this->term('2nd Semester');
        $token = $this->upload($this->csv("1,A,IT101,F2\n"));
        $this->postJson($this->url('store'), ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2, 'termId' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('termId');
        $this->period->update(['is_active' => false]);
        $other->update(['is_active' => true]);
        $this->period = $other;
        $b = $this->execute($this->csv("1,A,IT101,F2\n"));
        $this->assertSame(1, $b->success_count);
        $this->assertSame($old, ClassSubject::find($old['id'])->toArray());
        $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', 'a,b')])->assertUnprocessable();
        $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', 'a,b'), 'termId' => 99999])->assertUnprocessable();
    }

    public function test_class_row_transaction_rolls_back_new_section_on_failed_offering_write(): void
    {
        Event::listen('eloquent.creating: '.ClassSubject::class, fn () => throw new \RuntimeException('Synthetic write failure'));
        try {
            $b = $this->execute($this->csv("1,A,IT101,F1\n"));
        } finally {
            Event::forget('eloquent.creating: '.ClassSubject::class);
        }
        $this->assertSame(1, $b->failure_count);
        $this->assertDatabaseCount('class_sections', 0);
        $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_omitting_faculty_mapping_creates_tba_and_malformed_rows_have_correct_numbers(): void
    {
        $b = $this->execute($this->csv("1,A,IT101\nbad\n1,B,IT102\n"), ['year' => 0, 'block' => 1, 'subjectCode' => 2]);
        $this->assertSame(2, $b->success_count);
        $this->assertSame(1, $b->failure_count);
        $this->assertSame(3, $b->errors[0]['row']);
        $this->assertSame(2, ClassSubject::whereNull('faculty_id')->count());
    }

    public function test_tokens_cannot_cross_import_types_and_routes_enforce_roles_and_nested_programs(): void
    {
        $token = $this->postJson(route('programs.subjects.import.preview', [$this->department, $this->program]), ['file' => UploadedFile::fake()->createWithContent('s.csv', "code,name,year\nX,Demo,1")])->assertOk()->json('token');
        $this->postJson($this->url('store'), ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2, 'termId' => $this->period->id])->assertForbidden();
        $foreign = Department::create(['code' => 'X', 'name' => 'Other']);
        $this->postJson(route('programs.classes.import.preview', [$foreign, $this->program]))->assertNotFound();
        foreach (['student', 'office_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson($this->url('preview'))->assertForbidden();
            $this->postJson($this->url('store'))->assertForbidden();
        }
        $this->assertDatabaseCount('class_subjects', 0);
    }
    public function test_super_admin_can_import_xlsx_with_custom_mapping(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['Faculty', 'Subject', 'Block', 'Year'], ['F1', 'IT101', 'Z', 1]]);
        $path = storage_path('test-class.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
        $token = $this->postJson($this->url('preview'), ['file' => new UploadedFile($path, 'classes.xlsx', null, null, true), 'termId' => $this->period->id])->assertOk()->json('token');
        $this->postJson($this->url('store'), ['token' => $token, 'mapping' => ['facultyId' => 0, 'subjectCode' => 1, 'block' => 2, 'year' => 3], 'startRow' => 2, 'termId' => $this->period->id])->assertOk();
        $this->assertDatabaseHas('class_sections', ['block' => 'Z']);
        $this->assertDatabaseCount('class_subjects', 1);
    }

    public function test_class_upload_owner_program_and_mapping_guards_write_nothing(): void
    {
        $token = $this->upload($this->csv("1,A,IT101,F1\n"));
        $payload = ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2, 'termId' => $this->period->id];
        $admin = auth()->user();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->postJson($this->url('store'), $payload)->assertForbidden();
        $this->actingAs($admin);
        $other = $this->department->programs()->create(['code' => 'X', 'name' => 'Other']);
        $this->postJson(route('programs.classes.import.store', [$this->department, $other]), $payload)->assertForbidden();
        foreach ([['year' => 0, 'block' => 1], ['year' => 0, 'block' => 0, 'subjectCode' => 2], ['year' => 0, 'block' => 1, 'subjectCode' => 100]] as $mapping) {
            $this->postJson($this->url('store'), array_replace($payload, ['mapping' => $mapping]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('import_batches', 0);
        $this->assertDatabaseCount('class_sections', 0);
    }

}
