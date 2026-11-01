<?php
namespace Tests\Feature;
use App\Models\{User,Student,Department};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,DB};
use Tests\TestCase;
class StudentEmailIdentityTest extends TestCase
{
 use RefreshDatabase;
 public function test_admin_email_edit_updates_login_identity_invalidates_tokens_and_reset_uses_new_address(): void
 { $this->checkEmailRepair(false); }
 public function test_resaving_unchanged_profile_email_repairs_mismatched_login_email(): void
 { $this->checkEmailRepair(true); }
 private function checkEmailRepair(bool $profileAlreadyUpdated): void
 {
  \App\Models\ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>'2026-01-01','end_date'=>'2026-12-31','is_active'=>true]);
  Mail::fake();$admin=User::factory()->create(['role'=>'super_admin']);$u=User::factory()->create(['role'=>'student','email'=>'old@example.invalid']);$hash=$u->password;
  $d=Department::create(['code'=>'MAIL','name'=>'Synthetic']);$p=$d->programs()->create(['code'=>'MAIL','name'=>'Synthetic']);
  $s=$p->students()->create(['student_number'=>'MAIL1','email'=>$profileAlreadyUpdated ? 'new@example.invalid' : $u->email,'first_name'=>'Synthetic','last_name'=>'Student','user_id'=>$u->id,'is_irregular'=>false]);
  DB::table('password_reset_tokens')->insert(['email'=>$u->email,'token'=>'synthetic','created_at'=>now()]);
  $this->actingAs($admin)->putJson(route('programs.students.update',[$d,$p,$s]),['studentId'=>'MAIL1','email'=>'new@example.invalid','firstName'=>'Synthetic','lastName'=>'Student','isIrregular'=>false,'year'=>1,'block'=>'A'])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('new@example.invalid',$s->fresh()->email);$this->assertSame('new@example.invalid',$u->fresh()->email);$this->assertNull($u->fresh()->email_verified_at);$this->assertSame($hash,$u->fresh()->password);$this->assertDatabaseMissing('password_reset_tokens',['email'=>'old@example.invalid']);
  $controller=app(\App\Http\Controllers\Auth\PasswordResetCodeController::class);
  $controller->sendCode(new \Illuminate\Http\Request(['email'=>'new@example.invalid']));
  Mail::assertSent(\App\Mail\PasswordResetCode::class,fn($m)=>$m->hasTo('new@example.invalid'));
  Mail::assertSentCount(1);$controller->sendCode(new \Illuminate\Http\Request(['email'=>'old@example.invalid']));Mail::assertSentCount(1);
 }
}
