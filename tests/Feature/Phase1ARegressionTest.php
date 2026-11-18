<?php

namespace Tests\Feature;

use App\Models\ReviewPeriod;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Auth\AuthenticationTest;

class Phase1ARegressionTest extends AuthenticationTest
{
    // Reuse the existing authentication tests without running any migration/seeders.
    // Only this disposable connection is used; minimal fixtures cover the exercised routes.
    public function refreshDatabase(): void
    {
        config(['database.default' => 'phase1a', 'database.connections.phase1a' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'array']);
        DB::purge('phase1a');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutVite();
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\GenerateEvaluationNotifications::class]);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('id_number')->unique();
            $table->string('password');
            $table->string('role')->default('student');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('review_periods', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->string('semester');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_submission_open')->default(false);
            $table->timestamps();
            $table->unique(['academic_year', 'semester']);
        });
        Schema::create('staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_period_id');
        });
        foreach (['class_subjects', 'student_subject_enrollments'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_period_id')->nullable();
            });
        }
    }

    private function period(array $attributes = []): ReviewPeriod
    {
        return ReviewPeriod::create([...$this->payload(), ...$attributes]);
    }

    private function payload(): array
    {
        return ['academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31'];
    }

    public function test_creation_preserves_historical_labels_and_existing_period_state(): void
    {
        $old = $this->period(['academic_year' => '2010 - 2011', 'is_active' => true, 'is_submission_open' => true]);
        $before = $old->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('review-periods.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $created = ReviewPeriod::where('academic_year', '2026-2027')->firstOrFail();
        $this->assertSame('1st Semester', $created->semester);
        $this->assertSame('2026-08-01', $created->start_date->format('Y-m-d'));
        $this->assertSame('2026-12-31', $created->end_date->format('Y-m-d'));
        $this->assertFalse($created->is_active);
        $this->assertFalse($created->is_submission_open);
        $this->assertSame($before, $old->fresh()->getAttributes());
    }

    public function test_period_activation_open_close_and_deletion_are_unchanged(): void
    {
        $old = $this->period(['academic_year' => '2025-2026', 'is_active' => true]);
        $new = $this->period();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->patch(route('review-periods.activate', $new))->assertRedirect();
        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($new->fresh()->is_active);
        $this->patch(route('review-periods.toggle-submission', $new))->assertRedirect();
        $this->assertTrue($new->fresh()->is_submission_open);
        $this->patch(route('review-periods.toggle-submission', $new))->assertRedirect();
        $this->assertFalse($new->fresh()->is_submission_open);
        $this->delete(route('review-periods.destroy', $new))->assertRedirect();
        $this->assertDatabaseMissing('review_periods', ['id' => $new->id]);
        $this->assertSame('2025-2026', $old->fresh()->academic_year);
    }

    public function test_non_super_roles_cannot_create_activate_toggle_or_delete_periods(): void
    {
        $period = $this->period();
        foreach (['department_admin', 'office_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('review-periods.store'), $this->payload())->assertForbidden();
            $this->patch(route('review-periods.activate', $period))->assertForbidden();
            $this->patch(route('review-periods.toggle-submission', $period))->assertForbidden();
            $this->delete(route('review-periods.destroy', $period))->assertForbidden();
        }
        $this->assertDatabaseCount('review_periods', 1);
    }

    public function test_existing_semester_and_date_validation_is_preserved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->post(route('review-periods.store'), [...$this->payload(), 'semester' => 'Invalid', 'end_date' => '2026-07-01'])
            ->assertSessionHasErrors(['semester', 'end_date']);
        $this->assertDatabaseCount('review_periods', 0);
    }
}
