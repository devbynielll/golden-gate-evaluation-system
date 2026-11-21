<?php

namespace Tests\Feature;

use App\Models\ReviewPeriod;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewPeriodEditingTest extends Phase1ARegressionTest
{
    public function refreshDatabase(): void
    {
        parent::refreshDatabase();
        // Extend only the synthetic in-memory fixtures; never execute application migrations.
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
        });
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_period_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->string('evaluatee_type');
            $table->foreignId('class_subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('criteria_question_id');
            $table->unsignedTinyInteger('rating');
        });
    }

    private function details(array $overrides = []): array
    {
        return [...['academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31'], ...$overrides];
    }

    private function superAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_unused_period_can_be_corrected_without_changing_id_or_status_flags(): void
    {
        $this->superAdmin();
        $period = ReviewPeriod::create($this->details(['is_active' => true, 'is_submission_open' => true]));
        $this->put(route('review-periods.update', $period), $this->details([
            'academic_year' => '2027-2028', 'semester' => 'Summer', 'start_date' => '2027-04-01',
            'end_date' => '2027-06-30', 'is_active' => false, 'is_submission_open' => false,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $actual = $period->fresh();
        $this->assertSame($period->id, $actual->id);
        $this->assertSame('2027-2028', $actual->academic_year);
        $this->assertSame('Summer', $actual->semester);
        $this->assertSame('2027-04-01', $actual->start_date->format('Y-m-d'));
        $this->assertSame('2027-06-30', $actual->end_date->format('Y-m-d'));
        $this->assertTrue($actual->is_active);
        $this->assertTrue($actual->is_submission_open);
    }

    public function test_extension_preserves_faculty_office_answers_assignments_and_closed_status(): void
    {
        $this->superAdmin();
        $period = ReviewPeriod::create($this->details(['academic_year' => '2026 - 2027', 'is_active' => true]));
        DB::table('class_subjects')->insert(['id' => 1, 'review_period_id' => $period->id, 'faculty_id' => 41, 'subject_id' => 51]);
        DB::table('student_subject_enrollments')->insert(['id' => 1, 'review_period_id' => $period->id]);
        foreach (['faculty', 'office'] as $index => $type) {
            DB::table('evaluations')->insert(['id' => $index + 1, 'review_period_id' => $period->id, 'student_id' => 71,
                'evaluatee_type' => $type, 'class_subject_id' => $type === 'faculty' ? 1 : null,
                'office_id' => $type === 'office' ? 81 : null, 'comments' => 'Synthetic historical feedback', 'submitted_at' => '2026-11-01 12:00:00']);
            DB::table('evaluation_answers')->insert(['evaluation_id' => $index + 1, 'criteria_question_id' => $index + 1, 'rating' => $index + 3]);
        }
        $tables = ['evaluations', 'evaluation_answers', 'class_subjects', 'student_subject_enrollments'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $this->put(route('review-periods.update', $period), $this->details([
            'academic_year' => '2026 - 2027', 'start_date' => '2026-07-15', 'end_date' => '2027-01-31',
            'is_submission_open' => true, 'is_active' => false,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2027-01-31', $period->fresh()->end_date->format('Y-m-d'));
        $this->assertSame('2026-07-15', $period->fresh()->start_date->format('Y-m-d'));
        $this->assertSame('2026 - 2027', $period->fresh()->academic_year);
        $this->assertFalse($period->fresh()->is_submission_open);
        $this->assertTrue($period->fresh()->is_active);
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        $this->patch(route('review-periods.toggle-submission', $period))->assertRedirect();
        $this->assertTrue($period->fresh()->is_submission_open);
    }

    public function test_any_linked_evaluation_locks_identity_and_rejects_the_entire_edit(): void
    {
        $this->superAdmin();
        foreach ([null, '2026-11-01 12:00:00'] as $submitted) {
            $period = ReviewPeriod::create($this->details(['academic_year' => $submitted ? '2025-2026' : '2026-2027']));
            DB::table('evaluations')->insert(['review_period_id' => $period->id, 'student_id' => 71, 'evaluatee_type' => 'office', 'submitted_at' => $submitted]);
            $before = $period->fresh()->getAttributes();
            $this->put(route('review-periods.update', $period), $this->details([
                'academic_year' => '2028-2029', 'semester' => 'Summer', 'end_date' => '2027-01-31',
            ]))->assertSessionHasErrors(['academic_year', 'semester']);
            $this->assertSame($before, $period->fresh()->getAttributes());
        }
    }

    public function test_invalid_dates_and_duplicate_identity_do_not_write(): void
    {
        $this->superAdmin();
        $period = ReviewPeriod::create($this->details());
        ReviewPeriod::create($this->details(['semester' => 'Summer']));
        $before = $period->fresh()->getAttributes();
        foreach ([['end_date' => '2026-08-01'], ['end_date' => '2026-07-01'], ['end_date' => '2026-02-30'], ['start_date' => 'bad']] as $invalid) {
            $this->put(route('review-periods.update', $period), $this->details($invalid))->assertSessionHasErrors(array_keys($invalid));
            $this->assertSame($before, $period->fresh()->getAttributes());
        }
        $this->put(route('review-periods.update', $period), $this->details(['semester' => 'Summer']))->assertSessionHasErrors('academic_year');
        $this->assertSame($before, $period->fresh()->getAttributes());
    }

    public function test_dashboard_exposes_only_the_evaluation_existence_flag_for_edit_controls(): void
    {
        $this->superAdmin();
        $period = ReviewPeriod::create($this->details());
        DB::table('evaluations')->insert(['review_period_id' => $period->id, 'student_id' => 71, 'evaluatee_type' => 'office']);
        $dashboard = \Mockery::mock(\App\Services\AdminDashboardService::class);
        $dashboard->shouldReceive('activePeriod')->once()->andReturn($period);
        $dashboard->shouldReceive('feed')->once()->andReturn(['data' => collect()]);
        $dashboard->shouldReceive('stats')->once()->andReturn([]);
        $dashboard->shouldReceive('departmentBreakdown')->once()->andReturn([]);
        $dashboard->shouldReceive('filterOptions')->once()->andReturn([]);
        $this->app->instance(\App\Services\AdminDashboardService::class, $dashboard);
        $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['canManagePeriods']);
        $this->assertTrue($props['periods'][0]['evaluations_exists']);
        $this->assertArrayNotHasKey('evaluations', $props['periods'][0]);
    }

    public function test_only_verified_super_admin_can_edit_and_missing_period_is_not_created(): void
    {
        $period = ReviewPeriod::create($this->details());
        $this->put(route('review-periods.update', $period), $this->details())->assertRedirect(route('login'));
        foreach (['department_admin', 'office_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->put(route('review-periods.update', $period), $this->details())->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'super_admin']));
        $this->put(route('review-periods.update', $period), $this->details())->assertRedirect(route('verification.notice'));
        $this->superAdmin();
        $this->put(route('review-periods.update', 999), $this->details())->assertNotFound();
        $this->assertDatabaseCount('review_periods', 1);
    }
}
