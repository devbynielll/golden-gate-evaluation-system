<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, ImportBatch, ReviewPeriod, Student, User};
use Illuminate\Bus\{Batch, Batchable};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Artisan, Bus, Cache, DB, Mail, Storage};
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx};
use Tests\TestCase;

class UnifiedStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private $department;
    private $program;
    private $period;
    private $admin;
    private array $mapping = ['studentId'=>0,'email'=>1,'firstName'=>2,'lastName'=>3,'middleName'=>4,'suffix'=>5,'year'=>6,'block'=>7,'isIrregular'=>8];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); Storage::fake('local'); Mail::fake();
        $this->admin = User::factory()->create(['role'=>'department_admin']);
        $this->actingAs($this->admin);
        $this->department = Department::create(['code'=>'UNI','name'=>'Synthetic']);
        $this->program = $this->department->programs()->create(['code'=>'UNI','name'=>'Synthetic']);
        $this->period = ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>'2026-01-01','end_date'=>'2026-12-31','is_active'=>true,'is_submission_open'=>true]);
    }

    private function url(string $action): string
    {
        return route('programs.students.import.'.$action, [$this->department,$this->program]);
    }

    private function payload(array $rows): array
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray(array_merge([['STUDENT ID','EMAIL','FIRST NAME','LAST NAME','MIDDLE NAME','SUFFIX','YEAR LEVEL','BLOCK','IRREGULAR (Y/N)']], $rows));
        $path = Storage::path('synthetic.xlsx'); (new Xlsx($sheet))->save($path); $sheet->disconnectWorksheets();
        $token = $this->postJson($this->url('preview'), ['mode'=>'mixed','file'=>new UploadedFile($path,'students.xlsx',null,null,true)])->assertOk()->json('token');
        return ['token'=>$token,'mode'=>'mixed','startRow'=>2,'mapping'=>$this->mapping];
    }

    private function row(string $id, string $flag='N', string $year='1', string $block='A'): array
    {
        return [$id,strtolower($id).'@example.invalid','Synthetic','Student','','',$year,$block,$flag];
    }

    private function import(array $rows): ImportBatch
    {
        $id = $this->postJson($this->url('store'), $this->payload($rows))->assertOk()->json('batchId');
        return ImportBatch::findOrFail($id);
    }

    private function offerings(): array
    {
        $faculty = $this->program->faculty()->create(['faculty_id'=>'UNIF','name'=>'Synthetic Faculty']);
        $subject = $this->program->subjects()->create(['code'=>'UNIS','description'=>'Synthetic Subject','year_level'=>1]);
        $out = [];
        foreach (['A','B'] as $block) {
            $section = $this->program->classSections()->create(['year_level'=>1,'block'=>$block]);
            $out[] = $section->classSubjects()->create(['subject_id'=>$subject->id,'faculty_id'=>$faculty->id,'review_period_id'=>$this->period->id]);
        }
        return $out;
    }

    private function snapshot(): array
    {
        return collect(['users','students','student_subject_enrollments','evaluations','evaluation_answers'])->mapWithKeys(fn ($t)=>[$t=>DB::table($t)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_mixed_xlsx_review_confirm_save_and_progress_distinguish_types_and_preserve_regular_assignments(): void
    {
        [$a] = $this->offerings();
        $payload = $this->payload([$this->row('R','N'),$this->row('I','Y'),$this->row('BLANK','')]);
        $this->postJson($this->url('validate'),$payload)->assertOk()->assertJsonPath('summary.create',3)->assertJsonPath('summary.regular',2)->assertJsonPath('summary.irregular',1)->assertJsonPath('summary.subjectsToAdd',0);
        $this->assertDatabaseCount('students',0);
        $id = $this->postJson($this->url('store'),$payload)->assertOk()->json('batchId');
        $this->getJson(route('import-batches.show',$id))->assertOk()->assertJsonPath('status','completed')->assertJsonPath('regularCount',2)->assertJsonPath('irregularCount',1)->assertJsonPath('processedRows',3);
        foreach (['R','BLANK'] as $id) {
            $s = Student::where('student_number',$id)->firstOrFail();
            $this->assertFalse($s->is_irregular); $this->assertEquals([$a->id],$s->offerings()->pluck('id')->all());
            $this->assertNotNull($s->user->email_verified_at); $this->assertSame('student',$s->user->role);
        }
        $s = Student::where('student_number','I')->firstOrFail();
        $this->assertTrue($s->is_irregular); $this->assertNull($s->class_section_id); $this->assertSame(0,$s->offerings()->count());
        $this->assertDatabaseCount('student_subject_enrollments',0);
        Mail::assertQueued(\App\Mail\StudentAccountCreated::class,3);
    }

    public function test_irregular_manual_assignment_enables_only_valid_selected_faculty_evaluation(): void
    {
        [$a,$b] = $this->offerings(); $this->import([$this->row('I','Y')]);
        $s = Student::where('student_number','I')->firstOrFail();
        $q = CriteriaCategory::create(['evaluatee_type'=>'faculty','section_number'=>1,'name'=>'Teaching'])->questions()->create(['text'=>'Clear?','order'=>1]);
        $this->actingAs($s->user)->postJson(route('evaluation.faculty.store',$a),['ratings'=>[$q->id=>4]])->assertForbidden();
        $this->actingAs($this->admin)->putJson(route('programs.students.update',[$this->department,$this->program,$s]),[
            'studentId'=>$s->student_number,'email'=>$s->email,'firstName'=>$s->first_name,'lastName'=>$s->last_name,'isIrregular'=>true,
            'enrollments'=>[['subjectId'=>$a->subject_id,'classSectionId'=>$a->class_section_id]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$a->id],$s->fresh()->offerings()->pluck('id')->all());
        $this->actingAs($s->user)->get(route('evaluation.index'))->assertOk();
        $this->postJson(route('evaluation.faculty.store',$b),['ratings'=>[$q->id=>4]])->assertForbidden();
        $this->postJson(route('evaluation.faculty.store',$a),['ratings'=>[$q->id=>4]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('evaluations',1); $this->assertDatabaseHas('evaluation_answers',['criteria_question_id'=>$q->id,'rating'=>4]);
        $this->postJson(route('evaluation.faculty.store',$a),['ratings'=>[$q->id=>4]])->assertRedirect()->assertSessionHas('status', 'You already submitted this evaluation.');
        $this->assertDatabaseCount('evaluations',1);
    }

    public function test_reimport_existing_rows_and_history_are_not_changed_or_reclassified(): void
    {
        [$a] = $this->offerings(); $rows = [$this->row('R'),$this->row('I','Y')]; $this->import($rows);
        $s = Student::where('student_number','R')->firstOrFail();
        $e = Evaluation::create(['student_id'=>$s->id,'review_period_id'=>$this->period->id,'class_subject_id'=>$a->id,'evaluatee_type'=>'faculty','submitted_at'=>now()]);
        $q = CriteriaCategory::create(['evaluatee_type'=>'faculty','section_number'=>1,'name'=>'Teaching'])->questions()->create(['text'=>'Preserve','order'=>1]); $e->answers()->create(['criteria_question_id'=>$q->id,'rating'=>4]);
        $before = $this->snapshot();
        $batch = $this->import([$this->row('R','Y'),$this->row('I','N')]);
        $this->assertSame(2,$batch->skipped_count); $this->assertSame(0,$batch->success_count); $this->assertSame($before,$this->snapshot());
        Mail::assertQueued(\App\Mail\StudentAccountCreated::class,2);
    }

    public function test_invalid_fields_and_duplicates_produce_accurate_partial_results_without_orphan_accounts(): void
    {
        $badEmail = $this->row('BADMAIL'); $badEmail[1]='bad';
        $badName = $this->row('BADNAME'); $badName[2]='';
        $batch = $this->import([$this->row('R'),$this->row('R'),$this->row('I','Y','',''),$this->row('BADFLAG','maybe'),$this->row('NOYEAR','N','','A'),$this->row('','Y'),$badEmail,$badName]);
        $this->assertSame(2,$batch->success_count); $this->assertSame(5,$batch->failure_count); $this->assertSame(7,$batch->processed_rows);
        $this->assertSame(1,$batch->regular_count); $this->assertSame(1,$batch->irregular_count);
        $this->assertDatabaseCount('students',2); $this->assertSame(2,User::where('role','student')->count());
    }

    public function test_scope_mapping_and_existing_update_flags_cannot_bypass_roster_safety(): void
    {
        $payload = $this->payload([$this->row('I','Y')]);
        foreach (['validate','store'] as $action) {
            $this->postJson($this->url($action),$payload+['updateExisting'=>true])->assertUnprocessable();
            $bad=$payload; $bad['mapping']['subjectCodes']=9; $this->postJson($this->url($action),$bad)->assertUnprocessable();
            $bad=$payload; $bad['mode']='regular'; $this->postJson($this->url($action),$bad)->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create(['role'=>'super_admin']));
        $this->postJson($this->url('store'),$payload)->assertForbidden(); $this->assertDatabaseCount('students',0);
    }

    public function test_database_queued_multi_chunk_student_import_completes_and_reconciles_missing_callback(): void
    {
        config(['queue.default'=>'database']);
        $rows=[]; for ($i=0;$i<12;$i++) $rows[]=$this->row('QUEUE'.$i,$i%2?'Y':'N');
        $batch=$this->import($rows);
        $this->assertNotNull($batch->bus_batch_id); $this->assertDatabaseCount('jobs',3); $this->assertDatabaseCount('students',0);
        Artisan::call('queue:work',['connection'=>'database','--queue'=>'imports','--stop-when-empty'=>true,'--sleep'=>0,'--tries'=>1]);
        $this->assertDatabaseCount('students',12); $this->assertDatabaseCount('jobs',0); $this->assertDatabaseCount('failed_jobs',0);
        $batch->refresh(); $this->assertSame('completed',$batch->status); $this->assertSame(6,$batch->regular_count); $this->assertSame(6,$batch->irregular_count);
        $this->assertSame(0,Bus::findBatch($batch->bus_batch_id)->pendingJobs);
        $batch->update(['status'=>'processing']); // Interrupted completion callback must not strand saved records.
        $this->getJson(route('import-batches.show',$batch))->assertOk()->assertJsonPath('status','completed')->assertJsonPath('processedRows',12);
    }

    public function test_database_queued_faculty_import_and_completion_work_across_chunks_and_invalid_rows(): void
    {
        config(['queue.default'=>'database']);
        $rows="FACULTY ID,NAME\n"; for($i=0;$i<27;$i++) $rows.="QF$i,Synthetic Faculty $i\n"; $rows.="QF0,Duplicate\n";
        $base=[$this->department,$this->program];
        $token=$this->postJson(route('programs.faculty.import.preview',$base),['file'=>UploadedFile::fake()->createWithContent('faculty.csv',$rows)])->assertOk()->json('token');
        $id=$this->postJson(route('programs.faculty.import.store',$base),['token'=>$token,'mapping'=>['facultyId'=>0,'name'=>1],'startRow'=>2])->assertOk()->json('batchId');
        $this->assertDatabaseCount('jobs',2);
        Artisan::call('queue:work',['connection'=>'database','--queue'=>'imports','--stop-when-empty'=>true,'--sleep'=>0,'--tries'=>1]);
        $this->assertDatabaseCount('faculty',27); $this->assertDatabaseCount('failed_jobs',0);
        $this->getJson(route('import-batches.show',$id))->assertOk()->assertJsonPath('status','completed')->assertJsonPath('successCount',27)->assertJsonPath('failureCount',1)->assertJsonPath('errors.0.row',29);
    }

    public function test_failed_queue_job_is_reported_as_interrupted_not_falsely_complete_and_other_admin_is_denied(): void
    {
        config(['queue.default'=>'database']);
        $batch=ImportBatch::create(['type'=>'student','total_rows'=>2,'processed_rows'=>1,'success_count'=>1,'created_by'=>$this->admin->id]);
        $bus=Bus::batch([new UnifiedImportFailureProbe])->onQueue('imports')->dispatch();
        $batch->update(['bus_batch_id'=>$bus->id]);
        Artisan::call('queue:work',['connection'=>'database','--queue'=>'imports','--stop-when-empty'=>true,'--sleep'=>0,'--tries'=>1]);
        $this->assertDatabaseCount('failed_jobs',1);
        $this->getJson(route('import-batches.show',$batch))->assertOk()->assertJsonPath('status','failed')->assertJsonPath('processedRows',1);
        $this->actingAs(User::factory()->create(['role'=>'office_admin']))->getJson(route('import-batches.show',$batch))->assertForbidden();
    }

    public function test_row_transaction_failure_rolls_back_account_and_counter_without_affecting_next_record(): void
    {
        \Illuminate\Support\Facades\Event::listen('eloquent.created: '.Student::class, function($student) { if ($student->student_number==='BROKEN') throw new \RuntimeException('Synthetic failure'); });
        $batch=$this->import([$this->row('BROKEN'),$this->row('GOOD','Y')]);
        $this->assertSame(1,$batch->failure_count); $this->assertSame(1,$batch->irregular_count); $this->assertSame(0,$batch->regular_count);
        $this->assertDatabaseMissing('users',['id_number'=>'BROKEN']); $this->assertDatabaseCount('students',1);
    }
}

class UnifiedImportFailureProbe implements ShouldQueue
{
    use Batchable, Queueable;
    public function handle(): void { throw new \RuntimeException('Synthetic queue failure'); }
}
