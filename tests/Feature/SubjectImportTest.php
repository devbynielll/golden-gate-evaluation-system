<?php

namespace Tests\Feature;

use App\Models\{Department, ImportBatch, Program, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Tests\TestCase;

class SubjectImportTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Program $program;
    private array $mapping = ['code' => 0, 'description' => 1, 'year' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'department_admin']));
        $this->department = Department::create(['code' => 'CS', 'name' => 'Studies']);
        $this->program = $this->department->programs()->create(['code' => 'IT', 'name' => 'IT']);
    }

    private function url(string $action): string
    {
        return route('programs.subjects.import.'.$action, [$this->department, $this->program]);
    }

    private function upload(string $csv): string
    {
        return $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent('subjects.csv', $csv)])
            ->assertOk()->json('token');
    }

    private function execute(string $csv): ImportBatch
    {
        $id = $this->postJson($this->url('store'), ['token' => $this->upload($csv), 'mapping' => $this->mapping, 'startRow' => 2])
            ->assertOk()->json('batchId');

        return ImportBatch::findOrFail($id);
    }

    public function test_valid_subject_rows_create_without_credits_and_leave_other_programs_alone(): void
    {
        $other = $this->department->programs()->create(['code' => 'X', 'name' => 'Other']);
        $other->subjects()->create(['code' => 'IT101', 'description' => 'Other subject', 'year_level' => 2]);
        $batch = $this->execute("code,description,year\nIT101,Programming,1\nIT102,Mathematics,2\n");
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->success_count);
        $this->assertDatabaseHas('subjects', ['program_id' => $this->program->id, 'code' => 'IT101', 'credits' => null]);
        $this->assertSame('Other subject', $other->subjects()->first()->description);
    }

    public function test_duplicates_repeated_files_and_existing_credits_are_preserved(): void
    {
        $subject = $this->program->subjects()->create(['code' => 'IT101', 'description' => 'Programming', 'year_level' => 1, 'credits' => 3]);
        $before = $subject->fresh()->toArray();
        $csv = "code,description,year\nIT101,Programming,1\nIT102,Math,1\nIT102,Math,1\n";
        $first = $this->execute($csv);
        $this->assertSame(1, $first->success_count);
        $this->assertSame(2, $first->skipped_count);
        $again = $this->execute($csv);
        $this->assertSame(0, $again->success_count);
        $this->assertSame(3, $again->skipped_count);
        $this->assertSame($before, $subject->fresh()->toArray());
        $this->assertDatabaseCount('subjects', 2);
    }

    public function test_conflicting_file_rows_are_all_rejected_but_independent_valid_rows_import(): void
    {
        $batch = $this->execute("code,description,year\nIT101,Programming,1\nit101,Different,2\nIT102,Math,1\n");
        $this->assertSame(2, $batch->failure_count);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame([2, 3], array_column($batch->errors, 'row'));
        $this->assertDatabaseCount('subjects', 1);
    }

    public function test_existing_conflict_missing_fields_and_invalid_years_do_not_overwrite(): void
    {
        $subject = $this->program->subjects()->create(['code' => 'OLD', 'description' => 'Keep', 'year_level' => 1]);
        $batch = $this->execute("code,description,year\nOLD,Changed,1\n,No code,1\nEMPTY,,1\nBAD,Bad,7\nSHORT\nOK,Valid,6\n");
        $this->assertSame(5, $batch->failure_count);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame('Keep', $subject->fresh()->description);
        $this->assertSame(6, $batch->errors[4]['row']);
    }

    public function test_mapping_start_row_and_token_validation_writes_nothing(): void
    {
        $token = $this->upload("code,description,year\nA,Name,1\n");
        foreach ([['mapping' => ['code' => 0]], ['mapping' => ['code' => 0, 'description' => 0, 'year' => 2]], ['mapping' => ['code' => 100, 'description' => 1, 'year' => 2]], ['mapping' => $this->mapping + ['credits' => 3]], ['startRow' => 5]] as $override) {
            $this->postJson($this->url('store'), array_replace(['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2], $override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_upload_is_scoped_to_owner_program_type_and_single_use(): void
    {
        $token = $this->upload("code,description,year\nA,Name,1\n");
        $payload = ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 2];
        $admin = auth()->user();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->postJson($this->url('store'), $payload)->assertForbidden();
        $this->actingAs($admin);
        $other = $this->department->programs()->create(['code' => 'X', 'name' => 'Other']);
        $this->postJson(route('programs.subjects.import.store', [$this->department, $other]), $payload)->assertForbidden();
        $this->postJson($this->url('store'), $payload)->assertOk();
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('subjects', 1);
    }

    public function test_permissions_nested_ownership_and_batch_privacy_are_retained(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $batch = $this->execute("code,description,year\nA,Name,1\n");
        $otherDept = Department::create(['code' => 'X', 'name' => 'Other']);
        $this->postJson(route('programs.subjects.import.preview', [$otherDept, $this->program]))->assertNotFound();
        foreach (['student', 'office_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson($this->url('preview'))->assertForbidden();
            $this->postJson($this->url('store'))->assertForbidden();
        }
        $this->actingAs(User::where('role', 'department_admin')->sole())->getJson(route('import-batches.show', $batch))->assertForbidden();
    }

    public function test_xlsx_uses_the_existing_reader_and_custom_column_mapping(): void
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['Year', 'Name', 'Code'], [2, 'Spreadsheet subject', 'XLS1']]);
        $path = storage_path('test-subject.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
        $token = $this->postJson($this->url('preview'), ['file' => new UploadedFile($path, 'subjects.xlsx', null, null, true)])->assertOk()->json('token');
        $this->postJson($this->url('store'), ['token' => $token, 'mapping' => ['code' => 2, 'description' => 1, 'year' => 0], 'startRow' => 2])->assertOk();
        $this->assertDatabaseHas('subjects', ['code' => 'XLS1', 'year_level' => 2]);
    }

    public function test_empty_unreadable_and_oversized_files_are_rejected_without_batches(): void
    {
        foreach ([['blank.csv', "\n"], ['broken.xlsx', 'not a spreadsheet'], ['many.csv', str_repeat("A,B,C\n", 2001)]] as [$name, $content]) {
            $this->postJson($this->url('preview'), ['file' => UploadedFile::fake()->createWithContent($name, $content)])->assertUnprocessable();
        }
        $this->assertDatabaseCount('import_batches', 0);
    }
    public function test_existing_subject_offering_and_qualification_references_are_not_changed(): void
    {
        $subject = $this->program->subjects()->create(['code' => 'KEEP', 'description' => 'Original', 'year_level' => 1, 'credits' => 3]);
        $faculty = $this->program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Demo']);
        $faculty->qualifiedSubjects()->attach($subject->id);
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $section->classSubjects()->create(['subject_id' => $subject->id, 'faculty_id' => $faculty->id]);
        $before = [];
        foreach (['subjects', 'faculty_subject', 'class_subjects'] as $table) $before[$table] = DB::table($table)->get()->toJson();
        $batch = $this->execute("code,description,year\nKEEP,Replacement,2\n");
        $this->assertSame(1, $batch->failure_count);
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson());
    }

    public function test_subject_transaction_rolls_back_when_an_after_create_handler_fails(): void
    {
        Event::listen('eloquent.created: '.\App\Models\Subject::class, fn () => throw new \RuntimeException('Synthetic failure'));
        try {
            $batch = $this->execute("code,description,year\nA,Demo,1\n");
        } finally {
            Event::forget('eloquent.created: '.\App\Models\Subject::class);
        }
        $this->assertSame(1, $batch->failure_count);
        $this->assertDatabaseCount('subjects', 0);
    }

    public function test_super_admin_can_import_headerless_files_and_errors_keep_actual_row_numbers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $csv = "FIRST,Valid,1\n".str_repeat("DUP,Duplicate,1\n", 151).",Missing code,1\n";
        $token = $this->upload($csv);
        $id = $this->postJson($this->url('store'), ['token' => $token, 'mapping' => $this->mapping, 'startRow' => 1])->assertOk()->json('batchId');
        $batch = ImportBatch::findOrFail($id);
        $this->assertSame(153, $batch->processed_rows);
        $this->assertSame(153, $batch->errors[0]['row']);
        $this->assertSame(2, $batch->success_count);
        $this->assertSame(150, $batch->skipped_count);
    }

}
