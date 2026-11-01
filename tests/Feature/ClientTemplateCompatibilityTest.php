<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, Department, ImportBatch, Office, ReviewPeriod, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Mail, Storage};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ClientTemplateCompatibilityTest extends TestCase
{
    use RefreshDatabase;
    private $department;
    private $program;
    private $period;
    private const SUBJECTS=[['SUBJECT CODE','DESCRIPTION','YEAR LEVEL'],['S1','Synthetic Computing',1],['S2','Synthetic Communication',1]];
    private const FACULTY=[['FACULTY ID','NAME'],['F1','Mira Synthetic'],['F2','Paolo Synthetic']];
    private const CLASSES=[['YEAR LEVEL','BLOCK/SECTION','SUBJECT CODE','DESCRIPTION','FACULTY ID','FACULTY NAME'],[1,'C','S1','Synthetic Computing','F1','Mira Synthetic'],[1,'C','S2','Synthetic Communication','F2','Paolo Synthetic']];
    private const STUDENTS=[['STUDENT ID','EMAIL','FIRST NAME','LAST NAME','MIDDLE NAME','SUFFIX','YEAR LEVEL','BLOCK','IRREGULAR (Y/N)'],['IRR','irr@example.invalid','Maya','Synthetic','Alonzo','',1,'C','Y'],['REG','reg@example.invalid','Luis','Synthetic','Reyes','Jr.',1,'C','N']];
    private const OFFICES=[['DEPARTMENT','OFFICE CODE','OFFICE NAME'],['All Departments','GLOBAL','Synthetic Global Office'],['SYN - Synthetic Academic Department','LOCAL','Synthetic Local Office']];

    protected function setUp(): void
    {
        parent::setUp();Cache::flush();Storage::fake('local');Mail::fake();$this->actingAs(User::factory()->create(['role'=>'super_admin']));
        $this->department=Department::create(['code'=>'SYN','name'=>'Synthetic Academic Department']);$this->program=$this->department->programs()->create(['code'=>'SYN','name'=>'Synthetic']);
        $this->period=ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>'2026-08-01','end_date'=>'2027-01-31','is_active'=>true]);
    }
    private function xlsx(array $rows): UploadedFile
    {
        $book=new Spreadsheet();$book->getActiveSheet()->fromArray($rows,null,'A1',true);ob_start();(new Xlsx($book))->save('php://output');$bytes=ob_get_clean();$book->disconnectWorksheets();return UploadedFile::fake()->createWithContent('client-structure.xlsx',$bytes);
    }
    private function import(string $type,array $rows,?array $mapping=null,string $mode='irregular'): ImportBatch
    {
        $maps=['subjects'=>['code'=>0,'description'=>1,'year'=>2],'faculty'=>['facultyId'=>0,'name'=>1],'classes'=>['year'=>0,'block'=>1,'subjectCode'=>2,'description'=>3,'facultyId'=>4,'facultyName'=>5],'students'=>['studentId'=>0,'email'=>1,'firstName'=>2,'lastName'=>3,'middleName'=>4,'suffix'=>5,'year'=>6,'block'=>7,'isIrregular'=>8],'offices'=>['departmentCode'=>0,'code'=>1,'name'=>2]];
        $prefix=$type==='offices'?'offices':'programs.'.$type;$base=$type==='offices'?[]:[$this->department,$this->program];
        $extra=$type==='classes'?['termId'=>$this->period->id]:($type==='students'?['mode'=>$mode]:[]);
        $token=$this->postJson(route($prefix.'.import.preview',$base),$extra+['file'=>$this->xlsx($rows)])->assertOk()->json('token');
        $payload=$extra+['token'=>$token,'mapping'=>$mapping??$maps[$type],'startRow'=>2];
        if($type==='students')$this->postJson(route($prefix.'.import.validate',$base),$payload)->assertOk();
        $id=$this->postJson(route($prefix.'.import.store',$base),$payload)->assertOk()->json('batchId');return ImportBatch::findOrFail($id);
    }
    private function chain(): void
    {
        $this->assertSame(2,$this->import('subjects',self::SUBJECTS)->success_count);
        $this->assertSame(2,$this->import('faculty',self::FACULTY)->success_count);
        $this->assertSame(2,$this->import('classes',self::CLASSES)->success_count);
    }

    public function test_all_five_exact_client_header_structures_import_synthetic_relationships_and_both_student_flags(): void
    {
        $this->chain();$irregular=$this->import('students',self::STUDENTS);$this->assertSame(1,$irregular->success_count);$this->assertSame(1,$irregular->failure_count);
        $regular=$this->import('students',self::STUDENTS,mode:'regular');$this->assertSame(1,$regular->success_count);$this->assertSame(1,$regular->skipped_count);
        $this->assertSame(2,$this->import('offices',self::OFFICES)->success_count);
        $irr=Student::where('student_number','IRR')->firstOrFail();$reg=Student::where('student_number','REG')->firstOrFail();
        $this->assertTrue($irr->is_irregular);$this->assertNull($irr->class_section_id);$this->assertSame('Alonzo',$irr->middle_name);$this->assertSame(0,$irr->offerings()->count());
        $this->assertFalse($reg->is_irregular);$this->assertSame('Reyes',$reg->middle_name);$this->assertSame('Jr.',$reg->suffix);$this->assertSame('C',$reg->classSection->block);$this->assertCount(2,$reg->offerings()->get());
        $this->assertSame(['F1','F2'],ClassSubject::with('faculty')->get()->map(fn($o)=>$o->faculty->faculty_id)->all());
        $this->assertNull(Office::where('code','GLOBAL')->first()->department_id);$this->assertSame($this->department->id,Office::where('code','LOCAL')->first()->department_id);$this->assertDatabaseCount('student_subject_enrollments',0);
    }

    public function test_duplicate_imports_preserve_existing_records_and_relationships_with_accurate_results(): void
    {
        $this->chain();$this->import('students',self::STUDENTS);$this->import('offices',self::OFFICES);
        $tables=['subjects','faculty','class_sections','class_subjects','users','students','offices'];$snapshot=fn()=>collect($tables)->mapWithKeys(fn($t)=>[$t=>DB::table($t)->orderBy('id')->get()->toJson()])->all();$before=$snapshot();
        $this->assertSame(2,$this->import('subjects',self::SUBJECTS)->skipped_count);$this->assertSame(2,$this->import('faculty',self::FACULTY)->failure_count);$this->assertSame(2,$this->import('classes',self::CLASSES)->skipped_count);
        $rows=[self::STUDENTS[0],self::STUDENTS[1]];$this->assertSame(1,$this->import('students',$rows)->skipped_count);$this->assertSame(2,$this->import('offices',self::OFFICES)->failure_count);$this->assertSame($before,$snapshot());
    }

    public function test_class_description_and_name_are_reference_checks_and_never_authoritative_assignments(): void
    {
        $this->import('subjects',self::SUBJECTS);$this->import('faculty',self::FACULTY);
        $rows=[self::CLASSES[0],[1,'BAD-DESC','S1','Wrong description','F1','Mira Synthetic'],[1,'BAD-NAME','S1','Synthetic Computing','F1','Paolo Synthetic'],[1,'NAMEONLY','S1','Synthetic Computing','','Mira Synthetic'],[1,'GOOD','S1','Synthetic Computing','F1','Mira Synthetic']];
        $b=$this->import('classes',$rows);$this->assertSame(3,$b->failure_count);$this->assertSame(1,$b->success_count);$this->assertDatabaseCount('class_sections',1);$this->assertDatabaseCount('class_subjects',1);
        $this->assertSame('F1',ClassSubject::first()->faculty->faculty_id);$this->assertSame('Synthetic Computing',$this->program->subjects()->where('code','S1')->first()->description);
        // Existing four-field templates still work with unmapped reference columns.
        $b=$this->import('classes',[['YEAR LEVEL','BLOCK/SECTION','SUBJECT CODE','FACULTY ID'],[1,'OLD','S2','F2']],['year'=>0,'block'=>1,'subjectCode'=>2,'facultyId'=>3]);$this->assertSame(1,$b->success_count);
    }

    public function test_missing_class_dependencies_and_conflicting_reassignments_fail_without_corruption(): void
    {
        $b=$this->import('classes',self::CLASSES);$this->assertSame(2,$b->failure_count);$this->assertDatabaseCount('class_sections',0);
        $this->chain();$before=DB::table('class_subjects')->get()->toJson();
        $rows=[self::CLASSES[0],[1,'C','S1','Synthetic Computing','F2','Paolo Synthetic']];$this->assertSame(1,$this->import('classes',$rows)->failure_count);$this->assertSame($before,DB::table('class_subjects')->get()->toJson());
    }

    public function test_office_department_codes_full_names_full_labels_and_all_departments_are_resolved_exactly(): void
    {
        $rows=[self::OFFICES[0],['all departments','GLOBAL','Global'],['syn','CODE','By code'],['Synthetic Academic Department','NAME','By name'],['SYN - Synthetic Academic Department','LABEL','By full label'],['','BLANK','Legacy optional department']];
        $b=$this->import('offices',$rows);$this->assertSame(5,$b->success_count);$this->assertSame(0,$b->failure_count);
        foreach(['CODE','NAME','LABEL']as$code)$this->assertSame($this->department->id,Office::where('code',$code)->first()->department_id);
        foreach(['GLOBAL','BLANK']as$code)$this->assertNull(Office::where('code',$code)->first()->department_id);
    }

    public function test_office_unresolved_ambiguous_and_mismatched_full_labels_are_rejected_in_partial_import(): void
    {
        Department::create(['code'=>'A','name'=>'Ambiguous']);Department::create(['code'=>'B','name'=>'Ambiguous']);
        $rows=[self::OFFICES[0],['UNKNOWN','BAD1','No'],['Ambiguous','BAD2','No'],['SYN - Wrong full name','BAD3','No'],['All Departments','GOOD','Good']];
        $b=$this->import('offices',$rows);$this->assertSame(3,$b->failure_count);$this->assertSame(1,$b->success_count);$this->assertSame(4,$b->processed_rows);$this->assertDatabaseCount('offices',1);$this->assertNull(Office::first()->department_id);
    }
}
