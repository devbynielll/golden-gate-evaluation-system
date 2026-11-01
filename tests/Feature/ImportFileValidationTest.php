<?php

namespace Tests\Feature;

use App\Models\{Department, ImportBatch, ReviewPeriod, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\{Xls, Xlsx};
use Tests\TestCase;

class ImportFileValidationTest extends TestCase
{
    use RefreshDatabase;

    private $department;
    private $program;
    private $period;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'department_admin']));
        $this->department = Department::create(['code' => 'CSV22B', 'name' => 'Synthetic CSV checks']);
        $this->program = $this->department->programs()->create(['code' => 'CSV22B', 'name' => 'Synthetic']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    }

    private function file(string $contents, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv22b');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));
        return new UploadedFile($path, $name, null, null, true);
    }

    private function preview(string $type, UploadedFile $file)
    {
        $extra = $type === 'classes' ? ['termId' => $this->period->id] : ($type === 'students' ? ['mode' => 'regular'] : []);
        return $this->postJson(route('programs.'.$type.'.import.preview', [$this->department, $this->program]), ['file' => $file] + $extra);
    }

    private function import(string $type, string $csv, array $mapping): ImportBatch
    {
        $file = $this->file($csv);
        $this->assertSame('text/plain', $file->getMimeType(), $type.' must exercise actual text/plain detection');
        $token = $this->preview($type, $file)->assertOk()->json('token');
        $extra = $type === 'classes' ? ['termId' => $this->period->id] : ($type === 'students' ? ['mode' => 'regular'] : []);
        $id = $this->postJson(route('programs.'.$type.'.import.store', [$this->department, $this->program]), $extra + ['token' => $token, 'mapping' => $mapping, 'startRow' => 2])->assertOk()->json('batchId');
        return ImportBatch::findOrFail($id);
    }

    public function test_actual_plain_text_csv_imports_all_four_workflows_with_correct_links_and_original_duplicate_policy(): void
    {
        $imports = [
            'subjects' => ["code,description,year\nS22,Intro,1\n", ['code' => 0, 'description' => 1, 'year' => 2]],
            'faculty' => ["id,name\nF22,Ada\n", ['facultyId' => 0, 'name' => 1]],
            'classes' => ["year,block,subject,faculty\n1,A,S22,F22\n", ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3]],
            'students' => ["id,email,first,last,year,block\nST22,student22@example.invalid,Demo,Student,1,A\n", ['studentId' => 0, 'email' => 1, 'firstName' => 2, 'lastName' => 3, 'year' => 4, 'block' => 5]],
        ];
        foreach ($imports as $type => [$csv, $mapping]) $this->assertSame(1, $this->import($type, $csv, $mapping)->success_count);
        $student = Student::where('student_number', 'ST22')->firstOrFail();
        $this->assertSame('A', $student->classSection->block);
        $offering = $student->offerings()->with(['faculty', 'subject'])->sole();
        $this->assertSame('S22', $offering->subject->code);
        $this->assertSame('F22', $offering->faculty->faculty_id);
        $before = collect(['subjects','faculty','class_sections','class_subjects','students','student_subject_enrollments','users'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toJson()])->all();
        foreach ($imports as $type => [$csv, $mapping]) {
            $batch = $this->import($type, $csv, $mapping);
            $this->assertSame(1, $type === 'faculty' ? $batch->failure_count : $batch->skipped_count);
        }
        foreach ($before as $table => $snapshot) $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
    }

    public function test_empty_binary_markup_json_and_extension_disguises_are_rejected_by_all_four_workflows(): void
    {
        $bad = [
            ['', 'empty.csv'], [" \n\t", 'blank.csv'], ["\x00\x01not CSV", 'binary.csv'],
            ['<?php echo "not CSV";', 'script.csv'], ['<html><body>not CSV</body></html>', 'html.csv'],
            ['<?xml version="1.0"?><data/>', 'xml.csv'], ['{"id":"not CSV"}', 'json.csv'],
            ["code,name\nA,Alpha\n", 'unsupported.txt'], ["code,name\nA,Alpha\n", 'disguised.xlsx'],
            ["code,name\nA,Alpha\n", 'script.php'],
        ];
        foreach ($bad as [$content, $name]) foreach (['subjects','faculty','classes','students'] as $type) {
            $this->preview($type, $this->file($content, $name))->assertStatus(422)->assertJsonValidationErrors('file');
        }
        $book = new Spreadsheet(); $book->getActiveSheet()->fromArray([['id','name'],['F22','Ada']]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx22b'); (new Xlsx($book))->save($path); $bytes = file_get_contents($path); unlink($path); $book->disconnectWorksheets();
        foreach (['renamed.csv', 'renamed.docx', 'renamed.doc'] as $name) foreach (['subjects','faculty','classes','students'] as $type) $this->preview($type, $this->file($bytes, $name))->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('import_batches', 0); $this->assertDatabaseCount('subjects', 0); $this->assertDatabaseCount('faculty', 0); $this->assertDatabaseCount('students', 0);
    }

    public function test_existing_xls_and_xlsx_upload_support_remains_for_every_workflow(): void
    {
        foreach (['xls' => Xls::class, 'xlsx' => Xlsx::class] as $ext => $writer) {
            $book = new Spreadsheet(); $book->getActiveSheet()->fromArray([['id','name'],['F22','Ada']]);
            $path = tempnam(sys_get_temp_dir(), 'book22b'); (new $writer($book))->save($path); $bytes = file_get_contents($path); unlink($path); $book->disconnectWorksheets();
            foreach (['subjects','faculty','classes','students'] as $type) $this->assertSame(2, $this->preview($type, $this->file($bytes, 'existing.'.$ext))->assertOk()->json('totalRows'));
        }
    }

    public function test_quoted_multiline_bom_and_single_column_csv_remain_readable(): void
    {
        foreach (["\xEF\xBB\xBFid,name\nF22,\"Ada, Demo\"\n", "id,name\nF22,\"Ada\nDemo\"\n", "studentId\nST22\n", mb_convert_encoding("id,name\nF22,Ada\n", 'UTF-16LE', 'UTF-8')] as $i => $contents) {
            if ($i === 3) $contents = "\xFF\xFE".$contents;
            foreach (['subjects','faculty','classes','students'] as $type) $this->assertSame(2, $this->preview($type, $this->file($contents))->assertOk()->json('totalRows'));
        }
    }

    public function test_existing_five_megabyte_limit_is_retained_for_every_workflow(): void
    {
        foreach (['subjects','faculty','classes','students'] as $type) $this->preview($type, $this->file(str_repeat('A', 5 * 1024 * 1024 + 1024)))->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_new_csv_acceptance_does_not_bypass_role_or_program_permissions(): void
    {
        foreach (['office_admin','student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['subjects','faculty','classes','students'] as $type) $this->preview($type, $this->file("id,name\nF22,Ada\n"))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['subjects','faculty','classes','students'] as $type) $this->preview($type, $this->file("id,name\nF22,Ada\n"))->assertOk();
        $other = Department::create(['code' => 'OTHER22B', 'name' => 'Other']);
        $this->postJson(route('programs.subjects.import.preview', [$other, $this->program]), ['file' => $this->file("id,name\nF22,Ada\n")])->assertNotFound();
    }

    public function test_existing_word_support_is_retained_only_for_the_existing_shared_importer(): void
    {
        $word = new \PhpOffice\PhpWord\PhpWord(); $table = $word->addSection()->addTable();
        foreach ([['id','name'],['F22','Ada']] as $row) { $table->addRow(); foreach ($row as $cell) $table->addCell()->addText($cell); }
        $path = tempnam(sys_get_temp_dir(), 'word22b'); \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($path); $bytes = file_get_contents($path); unlink($path);
        foreach (['faculty','students'] as $type) $this->assertSame(2, $this->preview($type, $this->file($bytes, 'existing.docx'))->assertOk()->json('totalRows'));
        foreach (['subjects','classes'] as $type) $this->preview($type, $this->file($bytes, 'unsupported.docx'))->assertStatus(422)->assertJsonValidationErrors('file');
    }
}
