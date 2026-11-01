<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, ImportBatch, ReviewPeriod, Student, User};
use App\Services\StudentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Hash, Mail, Storage};
use Tests\TestCase;

class IrregularStudentImportTest extends TestCase
{
    use RefreshDatabase;
    private $department;
    private $program;
    private $period;
    private $admin;
    private array $mapping = ['studentId'=>0,'email'=>1,'firstName'=>2,'lastName'=>3,'middleName'=>4,'suffix'=>5,'year'=>6,'block'=>7,'isIrregular'=>8];

    protected function setUp(): void
    {
        parent::setUp();Cache::flush();Storage::fake('local');Mail::fake();
        $this->admin=User::factory()->create(['role'=>'department_admin']);$this->actingAs($this->admin);
        $this->department=Department::create(['code'=>'SYN','name'=>'Synthetic']);
        $this->program=$this->department->programs()->create(['code'=>'SYN','name'=>'Synthetic']);
        $this->period=ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>'2026-08-01','end_date'=>'2027-01-31','is_active'=>true,'is_submission_open'=>true]);
    }

    private function url(string $action): string { return route('programs.students.import.'.$action,[$this->department,$this->program]); }
    private function token(string $rows, string $mode='irregular'): string
    {
        return $this->postJson($this->url('preview'),['mode'=>$mode,'file'=>UploadedFile::fake()->createWithContent('students.csv',"STUDENT ID,EMAIL,FIRST NAME,LAST NAME,MIDDLE NAME,SUFFIX,YEAR LEVEL,BLOCK,IRREGULAR (Y/N)\n".$rows)])->assertOk()->json('token');
    }
    private function payload(string $token,string $mode='irregular'): array { return ['token'=>$token,'mapping'=>$this->mapping,'startRow'=>2,'mode'=>$mode]; }
    private function execute(string $rows,string $mode='irregular'): ImportBatch
    {
        $token=$this->token($rows,$mode);$id=$this->postJson($this->url('store'),$this->payload($token,$mode))->assertOk()->json('batchId');return ImportBatch::findOrFail($id);
    }
    private function snapshot(): array
    {
        return collect(['users','students','class_sections','class_subjects','student_subject_enrollments','evaluations','evaluation_answers'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->orderBy('id')->get()->toJson()])->all();
    }
    private function student(): Student { return Student::where('student_number','IRR')->firstOrFail(); }
    private function editPayload(Student $student,array $offerings): array
    {
        return ['studentId'=>$student->student_number,'email'=>$student->email,'firstName'=>$student->first_name,'lastName'=>$student->last_name,'isIrregular'=>true,'enrollments'=>array_map(fn($o)=>['subjectId'=>$o->subject_id,'classSectionId'=>$o->class_section_id],$offerings)];
    }
    private function editUrl(Student $student): string { return route('programs.students.update',[$this->department,$this->program,$student]); }
    private function offerings(): array
    {
        $faculty=$this->program->faculty()->create(['faculty_id'=>'F1','name'=>'Synthetic Professor']);$out=[];
        foreach(['A','B'] as$i=>$block){$subject=$this->program->subjects()->create(['code'=>'S'.($i+1),'description'=>'Synthetic '.$block,'year_level'=>1]);$section=$this->program->classSections()->create(['year_level'=>1,'block'=>$block]);$out[]=$section->classSubjects()->create(['subject_id'=>$subject->id,'faculty_id'=>$faculty->id,'review_period_id'=>$this->period->id]);}
        return $out;
    }

    public function test_review_and_import_basic_y_rows_never_infer_enrollment_from_year_and_block(): void
    {
        $this->offerings();$before=$this->snapshot();$token=$this->token("IRR,irr@example.invalid,Maya,Synthetic,Alonzo,Jr.,1,A,Y\n");
        $review=$this->postJson($this->url('validate'),$this->payload($token))->assertOk()->assertJsonPath('summary.create',1)->assertJsonPath('summary.subjectsToAdd',0);
        $this->assertArrayNotHasKey('term',$review->json());$this->assertNotEmpty($review->json('rows.0.warnings'));$this->assertSame($before,$this->snapshot());
        $id=$this->postJson($this->url('store'),$this->payload($token))->assertOk()->json('batchId');$b=ImportBatch::findOrFail($id);
        $s=$this->student();$this->assertTrue($s->is_irregular);$this->assertNull($s->class_section_id);$this->assertSame('Alonzo',$s->middle_name);$this->assertSame('Jr.',$s->suffix);
        $this->assertDatabaseCount('student_subject_enrollments',0);$this->assertSame(0,$s->offerings()->count());$this->assertSame(1,$b->success_count);$this->assertSame(0,$b->enrollments_count);
        $this->assertSame('student',$s->user->role);$this->assertNotNull($s->user->email_verified_at);Mail::assertQueued(\App\Mail\StudentAccountCreated::class,1);
    }

    public function test_y_and_n_modes_remain_explicit_with_accurate_partial_results(): void
    {
        $rows="IRR,irr@example.invalid,Maya,Synthetic,,,1,A,Y\nREG,reg@example.invalid,Luis,Synthetic,,,1,A,N\n";
        $b=$this->execute($rows);$this->assertSame(1,$b->success_count);$this->assertSame(1,$b->failure_count);$this->assertSame(2,$b->processed_rows);
        $b=$this->execute($rows,'regular');$this->assertSame(1,$b->success_count);$this->assertSame(1,$b->skipped_count);$this->assertFalse(Student::where('student_number','REG')->firstOrFail()->is_irregular);
        $this->assertDatabaseCount('student_subject_enrollments',0);
    }

    public function test_invalid_flags_missing_identity_and_conflicting_ids_report_failures_without_partial_accounts(): void
    {
        $b=$this->execute("BAD,bad-email,Maya,Synthetic,,,,,Y\nFLAG,flag@example.invalid,Maya,Synthetic,,,,,maybe\nEMPTY,empty@example.invalid,,Synthetic,,,,,Y\nDUP,dup@example.invalid,Maya,Synthetic,,,,,Y\nDUP,dup@example.invalid,Changed,Synthetic,,,,,Y\nIRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");
        $this->assertSame(4,$b->failure_count);$this->assertSame(1,$b->success_count);$this->assertDatabaseCount('students',1);$this->assertSame(1,User::where('role','student')->count());
    }

    public function test_existing_students_and_history_are_unchanged_even_with_case_variants_and_conflicting_flags(): void
    {
        $this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$s=$this->student();$o=$this->offerings()[0];
        $s->enrolledSubjects()->sync([$o->subject_id=>['class_section_id'=>$o->class_section_id]]);
        $e=Evaluation::create(['student_id'=>$s->id,'review_period_id'=>$this->period->id,'class_subject_id'=>$o->id,'evaluatee_type'=>'faculty','submitted_at'=>now()]);
        $q=CriteriaCategory::create(['evaluatee_type'=>'faculty','section_number'=>1,'name'=>'Teaching'])->questions()->create(['text'=>'Preserve','order'=>1]);$e->answers()->create(['criteria_question_id'=>$q->id,'rating'=>4]);
        $before=$this->snapshot();$b=$this->execute("irr,IRR@EXAMPLE.INVALID,Changed,Name,,,4,Z,N\n");$this->assertSame(1,$b->skipped_count);$this->assertSame($before,$this->snapshot());
        $b=$this->execute("IRR,other@example.invalid,Maya,Synthetic,,,,,Y\n");$this->assertSame(1,$b->failure_count);$this->assertSame($before,$this->snapshot());
    }

    public function test_duplicate_rows_create_once_and_existing_account_email_id_conflicts_are_rejected(): void
    {
        User::factory()->create(['id_number'=>'TAKEN','email'=>'taken@example.invalid']);
        $b=$this->execute("TAKEN,new@example.invalid,Maya,Synthetic,,,,,Y\nEMAIL,TAKEN@EXAMPLE.INVALID,Maya,Synthetic,,,,,Y\nIRR,irr@example.invalid,Maya,Synthetic,,,,,Y\nIRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");
        $this->assertSame(2,$b->failure_count);$this->assertSame(1,$b->success_count);$this->assertDatabaseCount('students',1);
        $before=$this->snapshot();$b=$this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$this->assertSame(1,$b->skipped_count);$this->assertSame($before,$this->snapshot());
    }

    public function test_scope_mode_and_removed_controls_cannot_be_forged(): void
    {
        $token=$this->token("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$payload=$this->payload($token);
        foreach(['validate','store'] as$action){
            $this->postJson($this->url($action),array_replace($payload,['mode'=>'regular']))->assertUnprocessable();
            $legacy=$payload;unset($legacy['mode']);$this->postJson($this->url($action),$legacy)->assertUnprocessable();
            $this->postJson($this->url($action),$payload+['updateExisting'=>true])->assertUnprocessable();
            $bad=$payload;$bad['mapping']['subjectCodes']=9;$this->postJson($this->url($action),$bad)->assertUnprocessable();
            $bad=$payload;unset($bad['mapping']['isIrregular']);$this->postJson($this->url($action),$bad)->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create(['role'=>'super_admin']));$this->postJson($this->url('store'),$payload)->assertForbidden();
        $this->assertDatabaseCount('students',0);$this->assertDatabaseCount('import_batches',0);
    }

    public function test_row_failure_rolls_back_account_and_student_but_other_valid_rows_still_import(): void
    {
        \Illuminate\Support\Facades\Event::listen('eloquent.created: '.User::class, function ($user) {
            if ($user->id_number === 'BROKEN') throw new \RuntimeException('Synthetic row failure');
        });
        $b=$this->execute("BROKEN,broken@example.invalid,Maya,Synthetic,,,,,Y\nIRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");
        $this->assertSame(1,$b->failure_count);$this->assertSame(1,$b->success_count);
        $this->assertDatabaseMissing('users',['id_number'=>'BROKEN']);$this->assertDatabaseMissing('students',['student_number'=>'BROKEN']);$this->assertDatabaseCount('students',1);
    }

    public function test_irregular_import_works_without_active_period_but_assignment_does_not(): void
    {
        $offerings=$this->offerings();$this->period->update(['is_active'=>false]);$b=$this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$this->assertSame(1,$b->success_count);
        $s=$this->student();$this->putJson($this->editUrl($s),$this->editPayload($s,[$offerings[0]]))->assertUnprocessable();$this->assertDatabaseCount('student_subject_enrollments',0);
    }

    public function test_manual_assignment_enables_only_selected_offerings_and_preserves_office_eligibility(): void
    {
        $offerings=$this->offerings();$this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$s=$this->student();
        $office=\App\Models\Office::create(['code'=>'OWN','name'=>'Synthetic office']);
        $before=app(StudentEvaluationService::class)->board($s,$this->period,1,1);$this->assertCount(0,$before['faculties']['data']);$this->assertCount(1,$before['offices']['data']);
        $this->actingAs($s->user)->postJson(route('evaluation.faculty.store',$offerings[0]),['ratings'=>[1=>4]])->assertForbidden();
        $this->actingAs($this->admin)->putJson($this->editUrl($s),$this->editPayload($s,[$offerings[0]]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$offerings[0]->id],$s->fresh()->offerings()->pluck('id')->all());$this->assertSame($offerings[0]->class_section_id,$s->enrolledSubjects()->first()->pivot->class_section_id);
        $board=app(StudentEvaluationService::class)->board($s->fresh(),$this->period,1,1);$this->assertCount(1,$board['faculties']['data']);$this->assertCount(1,$board['offices']['data']);
        $this->actingAs($s->user)->postJson(route('evaluation.faculty.store',$offerings[1]),['ratings'=>[1=>4]])->assertForbidden();
        $this->actingAs($this->admin)->putJson($this->editUrl($s),$this->editPayload($s,$offerings))->assertSessionHasNoErrors();$this->assertCount(2,$s->fresh()->enrolledSubjects()->get());
    }

    public function test_evaluated_offerings_cannot_be_removed_or_changed_and_historical_enrollments_survive(): void
    {
        $offerings=$this->offerings();$this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$s=$this->student();
        $past=ReviewPeriod::create(['academic_year'=>'2025-2026','semester'=>'2nd Semester','start_date'=>'2025-08-01','end_date'=>'2026-01-01']);
        $s->enrolledSubjects($past->id)->sync([$offerings[1]->subject_id=>['class_section_id'=>$offerings[1]->class_section_id]]);
        $this->putJson($this->editUrl($s),$this->editPayload($s,$offerings))->assertSessionHasNoErrors();
        $q=CriteriaCategory::create(['evaluatee_type'=>'faculty','section_number'=>1,'name'=>'Teaching'])->questions()->create(['text'=>'Clear?','order'=>1]);
        $this->actingAs($s->user)->postJson(route('evaluation.faculty.store',$offerings[0]),['ratings'=>[$q->id=>4]])->assertRedirect()->assertSessionHasNoErrors();
        $before=$this->snapshot();$this->actingAs($this->admin)->putJson($this->editUrl($s),$this->editPayload($s,[$offerings[1]]))->assertUnprocessable();$this->assertSame($before,$this->snapshot());
        $this->putJson($this->editUrl($s),$this->editPayload($s,[$offerings[0]]))->assertSessionHasNoErrors();$this->assertCount(1,$s->enrolledSubjects($past->id)->get());
        $this->actingAs($s->user)->postJson(route('evaluation.faculty.store',$offerings[0]),['ratings'=>[$q->id=>1]])->assertRedirect();$this->assertSame(4,DB::table('evaluation_answers')->value('rating'));
    }

    public function test_unauthorized_wrong_section_and_unscheduled_assignments_are_denied(): void
    {
        $offerings=$this->offerings();$this->execute("IRR,irr@example.invalid,Maya,Synthetic,,,,,Y\n");$s=$this->student();
        foreach(['student','office_admin']as$role){$this->actingAs(User::factory()->create(['role'=>$role]));$this->postJson($this->url('preview'),['mode'=>'irregular'])->assertForbidden();$this->putJson($this->editUrl($s),$this->editPayload($s,$offerings))->assertForbidden();}
        $this->actingAs($this->admin);$payload=$this->editPayload($s,[$offerings[0]]);$payload['enrollments'][0]['classSectionId']=$offerings[1]->class_section_id;$this->putJson($this->editUrl($s),$payload)->assertUnprocessable();
        $unscheduled=$this->program->subjects()->create(['code'=>'NONE','description'=>'Unscheduled','year_level'=>1]);$payload['enrollments']=[['subjectId'=>$unscheduled->id,'classSectionId'=>null]];$this->putJson($this->editUrl($s),$payload)->assertUnprocessable();
        $other=$this->department->programs()->create(['code'=>'OTHER','name'=>'Other']);$this->putJson(route('programs.students.update',[$this->department,$other,$s]),$this->editPayload($s,$offerings))->assertNotFound();$this->assertDatabaseCount('student_subject_enrollments',0);
    }
}
