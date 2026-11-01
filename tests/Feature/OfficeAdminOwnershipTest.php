<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, Student, User};
use App\Mail\AdminAccountCreated;
use App\Services\ReportService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Mail, Schema};
use Tests\TestCase;

class OfficeAdminOwnershipTest extends TestCase
{
    use RefreshDatabase;
    private $super;
    private $department;
    private $otherDepartment;
    private $office;
    private $other;
    private $global;
    private $period;

    protected function setUp(): void
    {
        parent::setUp(); Cache::flush(); Mail::fake();
        $this->super = User::factory()->create(['role' => 'super_admin']);
        $this->department = Department::create(['code' => 'D20', 'name' => 'Department20']);
        $this->otherDepartment = Department::create(['code' => 'OTHER20', 'name' => 'Other Department']);
        $this->office = Office::create(['code' => 'OWN', 'name' => 'Own Office', 'department_id' => $this->department->id]);
        $this->other = Office::create(['code' => 'OTHER', 'name' => 'Other Secret Office', 'department_id' => $this->otherDepartment->id]);
        $this->global = Office::create(['code' => 'GLOBAL', 'name' => 'Common Service']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $this->actingAs($this->super);
    }

    private function payload(?Office $office = null, string $suffix = '1'): array
    {
        return ['role' => 'office_admin', 'name' => 'Synthetic Admin'.$suffix, 'email' => 'admin'.$suffix.'@example.invalid', 'office_id' => ($office ?? $this->office)->id];
    }

    private function admin(?Office $office = null): User
    {
        return User::factory()->create(['role' => 'office_admin', 'office_id' => $office?->id]);
    }

    private function student($department, string $id): Student
    {
        $program = $department->programs()->firstOrCreate(['code' => 'P'.$id], ['name' => 'Synthetic']);
        $user = User::factory()->create(['role' => 'student']);
        return $program->students()->create(['user_id' => $user->id, 'student_number' => $id, 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => $id, 'is_irregular' => false]);
    }

    private function evaluation($student, $office, int $rating, string $comment, $period = null): Evaluation
    {
        $category = CriteriaCategory::firstOrCreate(['evaluatee_type' => 'office', 'section_number' => 1], ['name' => 'Shared office criteria']);
        $question = $category->questions()->firstOrCreate(['order' => 1], ['text' => 'Shared question']);
        $evaluation = Evaluation::create(['student_id' => $student->id, 'office_id' => $office->id, 'review_period_id' => ($period ?? $this->period)->id, 'evaluatee_type' => 'office', 'submitted_at' => now(), 'comments' => $comment]);
        $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => $rating]);
        return $evaluation;
    }

    public function test_super_can_create_separate_accounts_for_different_offices_and_department_rule_remains(): void
    {
        $this->post(route('accounts.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('accounts.store'), $this->payload($this->other, '2'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, User::where('role', 'office_admin')->count());
        $this->assertDatabaseHas('users', ['email' => 'admin1@example.invalid', 'office_id' => $this->office->id]);
        User::factory()->create(['role' => 'department_admin']);
        $this->post(route('accounts.store'), ['role' => 'department_admin', 'name' => 'No', 'email' => 'no@example.invalid'])->assertSessionHasErrors('role');
        Mail::assertSent(AdminAccountCreated::class, 2);
    }

    public function test_duplicate_and_missing_office_assignments_are_rejected_by_application_validation(): void
    {
        $this->admin($this->office);
        $this->post(route('accounts.store'), $this->payload())->assertSessionHasErrors('office_id');
        $p = $this->payload($this->other);unset($p['office_id']);
        $this->post(route('accounts.store'), $p)->assertSessionHasErrors('office_id');
        $this->post(route('accounts.store'), $this->payload($this->other))->assertSessionHasNoErrors();
        Mail::assertSent(AdminAccountCreated::class, 1);
    }

    public function test_database_unique_constraint_rejects_duplicate_office_claims_even_without_controller_validation(): void
    {
        $one = $this->admin($this->office);
        $rejected = false;
        try { DB::transaction(fn () => $this->admin($this->office)); }
        catch (UniqueConstraintViolationException) { $rejected = true; }
        $this->assertTrue($rejected);
        $this->assertSame(1, User::where('office_id', $this->office->id)->count());
        $this->assertDatabaseHas('users', ['id' => $one->id, 'office_id' => $this->office->id]);
        $indexes = Schema::getIndexes('users');
        $this->assertTrue(collect($indexes)->contains(fn ($index) => $index['name'] === 'users_office_assignment_unique' && $index['unique'] && $index['columns'] === ['office_id']));
    }

    public function test_creation_collision_after_validation_returns_a_validation_error_and_rolls_back(): void
    {
        // Deterministically exercise the race-error handler; this is not parallel MySQL execution.
        User::creating(function (User $user) {
            if ($user->email === 'admin1@example.invalid') {
                DB::table('users')->insert(['name' => 'Competing fixture', 'email' => 'competing@example.invalid', 'role' => 'office_admin', 'password' => 'synthetic-hash', 'office_id' => $user->office_id]);
            }
        });
        try {
            $this->post(route('accounts.store'), $this->payload())->assertSessionHasErrors('office_id');
            $this->assertDatabaseMissing('users', ['email' => 'admin1@example.invalid']);
            $this->assertDatabaseMissing('users', ['email' => 'competing@example.invalid']);
            Mail::assertNothingSent();
        } finally { User::flushEventListeners(); }
    }

    public function test_safe_reassignment_preserves_credentials_identity_and_evaluations_and_invalid_assignment_is_atomic(): void
    {
        $admin = $this->admin($this->office); $before = $admin->fresh()->getAttributes();
        $student = $this->student($this->department, 'S1');$eval = $this->evaluation($student, $this->office, 4, 'Own feedback');$answers = $eval->answers()->get()->toJson();
        $this->put(route('accounts.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'office_id' => $this->other->id])->assertRedirect()->assertSessionHasNoErrors();
        $fresh=$admin->fresh();$this->assertSame($this->other->id, $fresh->office_id);
        foreach (['password','id_number','email_verified_at','created_at','role'] as $key) $this->assertSame($before[$key], $fresh->getAttributes()[$key]);
        $this->assertSame($answers, $eval->answers()->get()->toJson());$this->assertSame($this->office->id,$eval->fresh()->office_id);
        $taken=$this->admin($this->global);$this->put(route('accounts.update',$admin), ['name'=>'Must not change','email'=>$admin->email,'office_id'=>$this->global->id])->assertSessionHasErrors('office_id');
        $this->assertSame($before['name'],$admin->fresh()->name);$this->assertSame($this->other->id,$admin->fresh()->office_id);
    }

    public function test_update_collision_after_validation_is_handled_without_partially_changing_the_account(): void
    {
        $admin=$this->admin($this->office);
        User::updating(function(User $user) { if($user->office_id===$this->other->id) DB::table('users')->insert(['name'=>'Competing','email'=>'update-race@example.invalid','role'=>'office_admin','password'=>'synthetic-hash','office_id'=>$this->other->id]); });
        try {
            $this->put(route('accounts.update',$admin), ['name'=>'New','email'=>$admin->email,'office_id'=>$this->other->id])->assertSessionHasErrors('office_id');
            $this->assertSame($this->office->id,$admin->fresh()->office_id);$this->assertDatabaseMissing('users',['email'=>'update-race@example.invalid']);
        } finally { User::flushEventListeners(); }
    }

    public function test_legacy_account_is_preserved_and_mapped_only_by_explicit_super_admin_edit(): void
    {
        $legacy=$this->admin();$before=$legacy->fresh()->getAttributes();
        $this->actingAs($legacy)->get(route('dashboard'))->assertForbidden();
        $this->get(route('reports.office'))->assertForbidden();
        $this->actingAs($this->super)->put(route('accounts.update',$legacy),['name'=>$legacy->name,'email'=>$legacy->email,'office_id'=>$this->office->id])->assertRedirect()->assertSessionHasNoErrors();
        $fresh=$legacy->fresh();$this->assertSame($this->office->id,$fresh->office_id);
        foreach(['id','password','id_number','role','created_at'] as $key)$this->assertSame($before[$key],$fresh->getAttributes()[$key]);
        $this->actingAs($legacy)->get(route('dashboard'))->assertOk();
    }

    public function test_own_office_dashboard_report_feedback_and_export_payload_are_isolated(): void
    {
        $own=$this->admin($this->office);$other=$this->admin($this->other);
        $a=$this->student($this->department,'S1');$b=$this->student($this->otherDepartment,'S2');
        $this->evaluation($a,$this->office,4,'Own feedback');$this->evaluation($b,$this->other,1,'Other confidential feedback');
        // Warm the unscoped Super report first; Office requests must not reuse it.
        $all=$this->get(route('reports.office'))->assertOk()->viewData('page')['props'];$this->assertCount(3,$all['offices']['data']);
        $props=$this->actingAs($own)->get(route('dashboard'))->assertOk()->viewData('page')['props'];$summary=$props['summary'];
        $this->assertSame('Own Office',$summary['officeName']);$this->assertSame(1,$summary['responses']);$this->assertSame(1,$summary['expected']);$this->assertSame(0,$summary['pending']);$this->assertSame(100,$summary['progress']);$this->assertSame('Own feedback',$summary['feedback']);$this->assertSame(4.0,(float)$summary['rating']);
        $props=$this->get(route('reports.office'))->assertOk()->viewData('page')['props'];$this->assertCount(1,$props['offices']['data']);$this->assertSame($this->office->id,$props['offices']['data'][0]['id']);$this->assertSame(1,$props['offices']['meta']['total']);$this->assertSame([],$props['filterOptions']['departments']);
        $this->assertStringNotContainsString('Other confidential',json_encode($props));$this->assertStringNotContainsString('Other Secret Office',json_encode($props));
        $otherProps=$this->actingAs($other)->get(route('reports.office'))->assertOk()->viewData('page')['props'];$this->assertSame($this->other->id,$otherProps['offices']['data'][0]['id']);$this->assertSame('Other confidential feedback',$otherProps['offices']['data'][0]['feedback']);
        $this->assertStringNotContainsString('Own feedback',json_encode($otherProps));
        $superProps=$this->actingAs($this->super)->get(route('reports.office'))->assertOk()->viewData('page')['props'];$this->assertCount(3,$superProps['offices']['data']);
    }

    public function test_manipulated_office_context_is_denied_and_search_page_filters_cannot_escape_scope(): void
    {
        $admin=$this->admin($this->office);$this->actingAs($admin);
        foreach(['dashboard','reports.office'] as $route)$this->get(route($route,['office_id'=>$this->other->id]))->assertForbidden();
        $props=$this->get(route('reports.office',['search'=>'Other Secret','page'=>1]))->assertOk()->viewData('page')['props'];$this->assertCount(0,$props['offices']['data']);$this->assertSame(0,$props['offices']['meta']['total']);
        $props=$this->get(route('reports.office',['office'=>$this->other->id,'id'=>$this->other->id]))->assertOk()->viewData('page')['props'];$this->assertSame($this->office->id,$props['offices']['data'][0]['id']);
        $this->get('/admin/offices/'.$this->other->id)->assertStatus(405);$this->put(route('offices.update',$this->other),['name'=>'Forged'])->assertForbidden();
    }

    public function test_reassignment_is_effective_on_next_request_even_with_warmed_caches_and_stale_user_relation(): void
    {
        $admin=$this->admin($this->office);$admin->load('office');$this->actingAs($admin)->get(route('dashboard'))->assertOk();$this->get(route('reports.office'))->assertOk();
        $this->actingAs($this->super)->put(route('accounts.update',$admin),['name'=>$admin->name,'email'=>$admin->email,'office_id'=>$this->other->id])->assertSessionHasNoErrors();
        $props=$this->actingAs($admin)->get(route('dashboard'))->assertOk()->viewData('page')['props'];$this->assertSame('Other Secret Office',$props['summary']['officeName']);
        $props=$this->get(route('reports.office'))->assertOk()->viewData('page')['props'];$this->assertSame($this->other->id,$props['offices']['data'][0]['id']);
        $this->get(route('reports.office',['office_id'=>$this->office->id]))->assertForbidden();
    }

    public function test_deleted_office_unassigns_admin_without_deleting_account_and_access_fails_closed(): void
    {
        $admin=$this->admin($this->office);$before=$admin->password;$this->office->delete();
        $this->assertDatabaseHas('users',['id'=>$admin->id,'office_id'=>null,'password'=>$before]);
        $this->actingAs($admin)->get(route('dashboard'))->assertForbidden();$this->get(route('reports.office'))->assertForbidden();
    }

    public function test_global_office_expected_count_uses_all_students_and_no_active_period_returns_no_data(): void
    {
        $admin=$this->admin($this->global);$a=$this->student($this->department,'S1');$this->student($this->otherDepartment,'S2');$this->evaluation($a,$this->global,3,'Common feedback');
        $props=$this->actingAs($admin)->get(route('dashboard'))->assertOk()->viewData('page')['props'];$this->assertSame(2,$props['summary']['expected']);$this->assertSame(1,$props['summary']['pending']);$this->assertSame(50,$props['summary']['progress']);
        $this->period->update(['is_active'=>false]);Cache::flush();$props=$this->get(route('dashboard'))->assertOk()->viewData('page')['props'];$this->assertSame(0,$props['summary']['responses']);$this->assertSame(0,$props['summary']['expected']);$this->assertSame('',$props['summary']['feedback']);
    }

    public function test_historical_period_reports_are_scoped_and_never_modify_answers(): void
    {
        $a=$this->student($this->department,'S1');$b=$this->student($this->otherDepartment,'S2');$past=ReviewPeriod::create(['academic_year'=>'2025-2026','semester'=>'2nd Semester','start_date'=>'2025-01-01','end_date'=>'2025-12-31']);
        $this->evaluation($a,$this->office,3,'Old own',$past);$this->evaluation($b,$this->other,1,'Old other',$past);$before=DB::table('evaluation_answers')->get()->toJson();
        $this->actingAs($this->admin($this->office));$props=$this->get(route('reports.office',['period_id'=>$past->id]))->assertOk()->viewData('page')['props'];$this->assertCount(1,$props['offices']['data']);$this->assertSame('Old own',$props['offices']['data'][0]['feedback']);$this->assertSame($before,DB::table('evaluation_answers')->get()->toJson());
    }

    public function test_non_super_roles_cannot_assign_offices_or_manage_accounts_and_faculty_criteria_stays_forbidden(): void
    {
        $admin=$this->admin($this->office);$before=$admin->fresh()->toArray();
        foreach(['office_admin','department_admin','student'] as $role){$user=$role==='office_admin'?$admin:User::factory()->create(['role'=>$role]);$this->actingAs($user);$this->get(route('accounts.index'))->assertForbidden();$this->post(route('accounts.store'),$this->payload($this->other))->assertForbidden();$this->put(route('accounts.update',$admin),['office_id'=>$this->other->id])->assertForbidden();}
        $this->actingAs($admin)->get(route('criteria.office'))->assertOk();
        $this->postJson('/admin/criteria/office/categories',['name'=>'Forbidden','section'=>1,'office_id'=>$this->other->id])->assertForbidden();
        $this->get(route('criteria.faculty'))->assertForbidden();
        $this->assertSame($before,$admin->fresh()->toArray());
    }

    public function test_account_index_shows_assignment_and_free_office_options_without_office_singleton(): void
    {
        $admin=$this->admin($this->office);$legacy=$this->admin();User::factory()->create(['role'=>'department_admin']);
        $props=$this->get(route('accounts.index'))->assertOk()->viewData('page')['props'];$this->assertEquals(['department_admin'],$props['takenRoles']);$this->assertCount(3,$props['offices']);$this->assertCount(1,$props['officeAssignments']);
        $row=collect($props['accounts']['data'])->firstWhere('id',$admin->id);$this->assertSame('Own Office',$row['office']['name']);$this->assertNull(collect($props['accounts']['data'])->firstWhere('id',$legacy->id)['office']);
    }

    public function test_email_failure_does_not_leave_an_office_claim_or_partial_account(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Synthetic email failure'));
        $this->post(route('accounts.store'),$this->payload())->assertRedirect();$this->assertDatabaseMissing('users',['email'=>'admin1@example.invalid']);$this->assertSame(0,User::where('office_id',$this->office->id)->count());
    }

    public function test_office_assignment_is_forbidden_for_department_admin_accounts(): void
    {
        $this->post(route('accounts.store'),['role'=>'department_admin','name'=>'Department','email'=>'dept@example.invalid','office_id'=>$this->office->id])->assertSessionHasErrors('office_id');
        $this->post(route('accounts.store'),array_replace($this->payload(),['office_id'=>99999]))->assertSessionHasErrors('office_id');
    }
}
