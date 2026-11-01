<?php
namespace Tests\Feature;
use App\Jobs\SendOfficeEvaluationReminder;
use App\Models\{Department,Evaluation,Office,ReviewPeriod,Student,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Mail,Queue};
use Tests\TestCase;
class OfficeReminderTest extends TestCase
{
 use RefreshDatabase;
 private function fixture(): array {
  Cache::flush();Mail::fake();$d=Department::create(['code'=>'REM','name'=>'Synthetic']);$p=$d->programs()->create(['code'=>'REM','name'=>'Synthetic']);
  $u=User::factory()->create(['role'=>'student']);$s=$p->students()->create(['student_number'=>'REM1','email'=>'synthetic@example.invalid','first_name'=>'Synthetic','last_name'=>'Student','user_id'=>$u->id,'is_irregular'=>true]);
  $period=ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>'2026-01-01','end_date'=>'2026-12-31','is_active'=>true,'is_submission_open'=>true]);
  $office=Office::create(['code'=>'REM','name'=>'Synthetic Office']);$other=Office::create(['code'=>'OTHER','name'=>'Other Office']);$a=User::factory()->create(['role'=>'office_admin','office_id'=>$office->id]);return [$s,$period,$office,$other,$a];
 }
 public function test_office_admin_can_queue_own_reminders_but_not_forged_office_or_faculty(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();Queue::fake();$this->actingAs($a);
  $this->post(route('students.remind',$s),['type'=>'office','office_id'=>$other->id])->assertForbidden();
  $this->post(route('students.remind',$s),['type'=>'faculty'])->assertForbidden();
  $this->post(route('students.remind',$s),['type'=>'office'])->assertRedirect();
  $this->post(route('students.remind',$s),['type'=>'office'])->assertRedirect();
  Queue::assertPushed(SendOfficeEvaluationReminder::class,1);Queue::assertPushed(SendOfficeEvaluationReminder::class,fn($j)=>$j->officeId===$o->id && $j->actorId===$a->id);
 }
 public function test_worker_rechecks_completion_and_assignment_and_never_sends_other_office_names(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();$job=new SendOfficeEvaluationReminder($s->id,$p->id,$o->id,$a->id);$job->handle();$job->handle();
  Mail::assertSentCount(1);Mail::assertSent(\App\Mail\EvaluationReminder::class,fn($m)=>$m->pending===[$o->name]);
  Cache::flush();Evaluation::create(['student_id'=>$s->id,'review_period_id'=>$p->id,'office_id'=>$o->id,'evaluatee_type'=>'office','submitted_at'=>now()]);$job->handle();Mail::assertSentCount(1);
  $this->assertSame([],app(\App\Services\AdminDashboardService::class)->officeFeed($o,$p,null,null,1)['data']->all());
 }
 public function test_bulk_uses_only_pending_assigned_office_and_unassigned_admin_is_denied(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();Queue::fake();
  $this->actingAs($a)->postJson(route('evaluations.remind-all'),['office_id'=>$other->id])->assertForbidden();
  $this->postJson(route('evaluations.remind-all'),[])->assertOk();
  Queue::assertPushed(\App\Jobs\SendEvaluationReminderRowsJob::class);
  $a->update(['office_id'=>null]);$this->postJson(route('evaluations.remind-all'),[])->assertForbidden();
 }
 public function test_completed_or_closed_period_does_not_queue_and_student_is_denied(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();Queue::fake();$p->update(['is_submission_open'=>false]);
  $this->actingAs($a)->post(route('students.remind',$s),['type'=>'office'])->assertRedirect();Queue::assertNothingPushed();
  $this->actingAs($s->user)->post(route('students.remind',$s),['type'=>'office'])->assertForbidden();
 }

 public function test_database_queue_bulk_rechecks_and_sends_only_assigned_office_once(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();config(['queue.default'=>'database']);
  $id=$this->actingAs($a)->postJson(route('evaluations.remind-all'),[])->assertOk()->json('batchId');
  \Illuminate\Support\Facades\Artisan::call('queue:work',['connection'=>'database','--queue'=>'imports','--stop-when-empty'=>true,'--sleep'=>0,'--tries'=>1]);
  Mail::assertNothingSent();
  \Illuminate\Support\Facades\Artisan::call('queue:work',['connection'=>'database','--queue'=>'default','--stop-when-empty'=>true,'--sleep'=>0,'--tries'=>1]);
  Mail::assertSentCount(1);Mail::assertSent(\App\Mail\EvaluationReminder::class,fn($m)=>$m->pending===[$o->name]);
  $this->getJson(route('import-batches.show',$id))->assertOk()->assertJsonPath('status','completed');$this->assertDatabaseCount('failed_jobs',0);
 }
 public function test_worker_does_not_send_when_administrator_is_reassigned_after_queueing(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();$job=new SendOfficeEvaluationReminder($s->id,$p->id,$o->id,$a->id);
  $a->update(['office_id'=>$other->id]);$job->handle();Mail::assertNothingSent();
 }
 public function test_office_from_another_department_has_no_eligible_recipient(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();$foreign=Department::create(['code'=>'FOREIGN','name'=>'Synthetic foreign']);$o->update(['department_id'=>$foreign->id]);
  $this->assertSame([],SendOfficeEvaluationReminder::pending($s,$p,$o->id));
  $this->assertCount(0,app(\App\Services\AdminDashboardService::class)->officeFeed($o,$p,null,null,1)['data']);
 }

 public function test_office_review_period_is_actual_and_read_only_for_open_closed_and_missing_period(): void {
  [$s,$p,$o,$other,$a]=$this->fixture();$this->actingAs($a);
  $this->get(route('dashboard'))->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->component('Admin/OfficeDashboard')->where('isReviewOpen',true)->where('periods.0.semester',$p->semester)->where('periods.0.academic_year',$p->academic_year)->where('periods.0.is_active',true)->where('periods.0.start_date','2026-01-01')->where('periods.0.end_date','2026-12-31'));
  $this->patch(route('review-periods.toggle-submission',$p))->assertForbidden();$this->patch(route('review-periods.activate',$p))->assertForbidden();
  $p->update(['is_submission_open'=>false]);
  $this->get(route('dashboard'))->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->where('isReviewOpen',false)->has('periods',1));
  $p->update(['is_active'=>false]);
  $this->get(route('dashboard'))->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->where('isReviewOpen',false)->has('periods',0));
 }
}
