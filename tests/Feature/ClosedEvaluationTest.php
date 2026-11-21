<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, StaffCriteriaCategory, StaffEvaluation, User};
use App\Services\StudentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

class ClosedEvaluationTest extends TestCase
{
    use RefreshDatabase;
    private $student;
    private ReviewPeriod $period;
    private User $admin;
    private array $targets;
    private array $ratings;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); Cache::flush();
        $department=Department::create(['code'=>'SYN','name'=>'Synthetic Department']);
        $program=$department->programs()->create(['code'=>'SYN','name'=>'Synthetic Program']);
        $section=$program->classSections()->create(['year_level'=>1,'block'=>'A']);
        $subject=$program->subjects()->create(['code'=>'SYN101','description'=>'Synthetic Subject','year_level'=>1]);
        $faculty=$program->faculty()->create(['faculty_id'=>'SYN-F','name'=>'Synthetic Faculty']);
        $this->period=ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>now()->subMonth(),'end_date'=>now()->subDay(),'is_active'=>true,'is_submission_open'=>true]);
        $offering=ClassSubject::create(['review_period_id'=>$this->period->id,'class_section_id'=>$section->id,'subject_id'=>$subject->id,'faculty_id'=>$faculty->id]);
        $office=Office::create(['code'=>'SYN','name'=>'Synthetic Office']);
        $staff=$office->staff()->create(['name'=>'Synthetic Staff','position'=>'Assistant']);
        $user=User::factory()->create(['role'=>'student']);
        $this->student=$program->students()->create(['user_id'=>$user->id,'student_number'=>'SYN001','email'=>$user->email,'first_name'=>'Synthetic','last_name'=>'Student','class_section_id'=>$section->id]);
        $this->admin=User::factory()->create(['role'=>'super_admin']);
        $this->targets=['faculty'=>$offering,'office'=>$office,'staff'=>$staff];
        foreach (['faculty','office','staff'] as $type) {
            $category=$type==='staff' ? StaffCriteriaCategory::create(['section_number'=>1,'name'=>'Synthetic Staff Criteria']) : CriteriaCategory::create(['evaluatee_type'=>$type,'section_number'=>1,'name'=>'Synthetic '.$type]);
            $question=$category->questions()->create(['text'=>'Synthetic criterion','order'=>1]);
            $this->ratings[$type]=[$question->id=>4];
        }
    }

    private function postEvaluation(string $type, bool $json = true)
    {
        $url=route('evaluation.'.$type.'.store',$this->targets[$type]);
        $payload=['ratings'=>$this->ratings[$type],'comments'=>''];
        return $json ? $this->postJson($url,$payload) : $this->post($url,$payload);
    }

    private function assertNoResponses(): void
    {
        foreach (['evaluations','evaluation_answers','staff_evaluations','staff_evaluation_answers'] as $table) $this->assertDatabaseCount($table,0);
    }

    public function test_closed_and_missing_period_requests_fail_for_every_type_without_success_flash(): void
    {
        foreach ([false,true] as $missing) {
            $this->period->update(['is_submission_open'=>false,'is_active'=>!$missing]);
            $this->actingAs($this->student->user);
            $props=$this->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
            $this->assertFalse($props['isSubmissionOpen']);
            foreach (array_keys($this->targets) as $type) {
                session()->forget('status');
                $response=$this->postEvaluation($type);
                if ($missing && $type==='faculty') $response->assertForbidden(); // No eligible offering without an active period.
                else $response->assertUnprocessable()->assertJsonValidationErrors($type==='staff'?'staff':'evaluation');
                if (!$missing || $type!=='faculty') $this->postEvaluation($type,false)->assertSessionHasErrors()->assertSessionMissing('status');
                $this->assertNoResponses();
            }
        }
    }

    public function test_open_expired_dates_submit_normally_duplicates_do_not_overwrite_and_extension_does_not_reopen(): void
    {
        $this->actingAs($this->student->user);
        foreach (array_keys($this->targets) as $type) {
            $this->postEvaluation($type,false)->assertSessionHasNoErrors(); // End Date is yesterday; explicit flag governs.
            session()->forget('status');
            $this->postEvaluation($type,false)->assertSessionHasErrors()->assertSessionMissing('status');
        }
        $this->assertDatabaseCount('evaluations',2); $this->assertDatabaseCount('staff_evaluations',1);
        $before=[];foreach(['evaluations','evaluation_answers','staff_evaluations','staff_evaluation_answers'] as $table)$before[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        $this->actingAs($this->admin)->patch(route('review-periods.toggle-submission',$this->period))->assertRedirect();
        $this->put(route('review-periods.update',$this->period),['academic_year'=>$this->period->academic_year,'semester'=>$this->period->semester,'start_date'=>$this->period->start_date->toDateString(),'end_date'=>now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
        $this->assertFalse($this->period->fresh()->is_submission_open);
        foreach($before as $table=>$rows)$this->assertSame($rows,DB::table($table)->orderBy('id')->get()->toJson());
    }

    public function test_previously_open_forms_are_rejected_after_authorized_closure_and_reopening_is_explicit(): void
    {
        $props=$this->actingAs($this->student->user)->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['isSubmissionOpen']);
        $this->actingAs($this->admin)->patch(route('review-periods.toggle-submission',$this->period))->assertRedirect();
        $this->actingAs($this->student->user);
        foreach(array_keys($this->targets) as $type)$this->postEvaluation($type)->assertUnprocessable();
        $this->assertNoResponses();
        $this->actingAs($this->admin)->patch(route('review-periods.toggle-submission',$this->period))->assertRedirect();
        $this->actingAs($this->student->user);
        foreach(array_keys($this->targets) as $type)$this->postEvaluation($type,false)->assertSessionHasNoErrors();
    }

    public function test_closure_between_initial_check_and_locked_recheck_cannot_create_any_type_of_response(): void
    {
        foreach(array_keys($this->targets) as $type) {
            $this->period->update(['is_active'=>true,'is_submission_open'=>true]);
            $calls=0;
            $service=\Mockery::mock(StudentEvaluationService::class)->makePartial();
            $service->shouldReceive('activePeriod')->twice()->andReturnUsing(function () use (&$calls) {
                if (++$calls===2) $this->period->update(['is_submission_open'=>false]);
                return $this->period->fresh();
            });
            $this->app->instance(StudentEvaluationService::class,$service);
            $this->actingAs($this->student->user);
            $this->postEvaluation($type)->assertUnprocessable()->assertJsonValidationErrors($type==='staff'?'staff':'evaluation');
            $this->assertNoResponses();
        }
    }
}
