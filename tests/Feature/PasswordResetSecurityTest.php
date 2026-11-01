<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash,Mail};
use Tests\TestCase;
class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;
    public function test_missing_fields_missing_rows_and_invalid_final_tokens_are_rejected(): void
    {
        $this->postJson('/forgot-password/verify-code',[])->assertUnprocessable();
        $this->postJson('/forgot-password/reset-with-code',[])->assertUnprocessable();
        $user=User::factory()->create();$old=$user->password;
        $payload=['email'=>$user->email,'reset_token'=>str_repeat('B',64),'password'=>'Synthetic-New-Password!42','password_confirmation'=>'Synthetic-New-Password!42'];
        $this->postJson('/forgot-password/reset-with-code',$payload)->assertUnprocessable();
        DB::table('password_reset_tokens')->insert(['email'=>$user->email,'token'=>Hash::make(str_repeat('A',64)),'created_at'=>now()]);
        $this->postJson('/forgot-password/reset-with-code',$payload)->assertUnprocessable();
        DB::table('password_reset_tokens')->where('email',$user->email)->update(['token'=>Hash::make($payload['reset_token']),'created_at'=>null]);
        $this->postJson('/forgot-password/reset-with-code',$payload)->assertUnprocessable();
        $this->assertSame($old,$user->fresh()->password);
    }
    public function test_send_verify_reset_complete_http_journey(): void
    {
        Mail::fake();$user=User::factory()->create();
        $this->postJson('/forgot-password/code',['email'=>$user->email])->assertOk();
        $code=Mail::sent(\App\Mail\PasswordResetCode::class)->sole()->code;
        $this->assertNotSame($code,DB::table('password_reset_tokens')->where('email',$user->email)->value('token'));
        $token=$this->postJson('/forgot-password/verify-code',['email'=>$user->email,'code'=>$code])->assertOk()->json('reset_token');
        $this->postJson('/forgot-password/reset-with-code',['email'=>$user->email,'reset_token'=>$token,'password'=>'Synthetic-New-Password!42','password_confirmation'=>'Synthetic-New-Password!42'])->assertOk();
        $this->post('/login',['id_number'=>$user->id_number,'password'=>'Synthetic-New-Password!42'])->assertRedirect();$this->assertAuthenticatedAs($user);
    }
    public function test_complete_code_and_reset_boundaries_and_replay(): void
    {
        Mail::fake();$this->travelTo(now()->startOfSecond());
        foreach ([0=>true,9=>true,10=>false,11=>false,-1=>false] as $age=>$valid) {
            $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.($age+20)]);
            $u=User::factory()->create();DB::table('password_reset_tokens')->insert(['email'=>$u->email,'token'=>Hash::make('123456'),'created_at'=>now()->subMinutes($age)]);
            $r=$this->postJson('/forgot-password/verify-code',['email'=>$u->email,'code'=>'123456']);
            if(!$valid){$r->assertUnprocessable();continue;}
            $token=$r->assertOk()->json('reset_token');$this->assertSame(64,strlen($token));
            $this->postJson('/forgot-password/verify-code',['email'=>$u->email,'code'=>'123456'])->assertUnprocessable();
            $this->postJson('/forgot-password/verify-code',['email'=>$u->email,'code'=>$token])->assertUnprocessable();
            $payload=['email'=>$u->email,'reset_token'=>$token,'password'=>'Synthetic-New-Password!42','password_confirmation'=>'Synthetic-New-Password!42'];
            $this->postJson('/forgot-password/reset-with-code',$payload)->assertOk();$this->assertTrue(Hash::check($payload['password'],$u->fresh()->password));
            $this->postJson('/forgot-password/reset-with-code',$payload)->assertUnprocessable();
        }
    }
    public function test_final_token_expiry_future_missing_invalid_and_direct_code_bypass(): void
    {
        $this->travelTo(now()->startOfSecond());
        foreach ([0=>true,9=>true,10=>false,11=>false,-1=>false] as $age=>$valid) {
            $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.($age+20)]);
            $u=User::factory()->create();$old=$u->password;$token=str_repeat('A',64);DB::table('password_reset_tokens')->insert(['email'=>$u->email,'token'=>Hash::make($token),'created_at'=>now()->subMinutes($age)]);
            $payload=['email'=>$u->email,'reset_token'=>$token,'password'=>'Synthetic-New-Password!42','password_confirmation'=>'Synthetic-New-Password!42'];$r=$this->postJson('/forgot-password/reset-with-code',$payload);
            if($valid)$r->assertOk();else{$r->assertUnprocessable();$this->assertSame($old,$u->fresh()->password);}
        }
        $u=User::factory()->create();DB::table('password_reset_tokens')->insert(['email'=>$u->email,'token'=>Hash::make('123456'),'created_at'=>now()]);
        $this->postJson('/forgot-password/reset-with-code',['email'=>$u->email,'reset_token'=>'123456','password'=>'Synthetic-New-Password!42','password_confirmation'=>'Synthetic-New-Password!42'])->assertUnprocessable();
        $this->postJson('/forgot-password/verify-code',['email'=>$u->email,'code'=>'654321'])->assertUnprocessable();
        $this->postJson('/forgot-password/verify-code',['email'=>'missing@example.invalid','code'=>'123456'])->assertUnprocessable();
    }
}
