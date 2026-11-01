<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, User};
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class OfficeCriteriaMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
    }

    public function test_populated_upgrade_and_rollback_preserve_shared_records_and_constraints(): void
    {
        $migration = require database_path('migrations/2026_11_01_000001_add_office_scope_to_criteria_categories.php');
        $migration->down();
        $category = CriteriaCategory::create(['evaluatee_type' => 'office', 'name' => 'Existing shared', 'section_number' => 1]);
        $question = $category->questions()->create(['text' => 'Original question', 'order' => 1]);
        $office = Office::create(['code' => 'LEGACY', 'name' => 'Existing office']);
        $department = Department::create(['code' => 'TEST', 'name' => 'Existing department']);
        $program = $department->programs()->create(['code' => 'TEST', 'name' => 'Existing program']);
        $user = User::factory()->create(['role' => 'student']);
        $student = $program->students()->create(['user_id' => $user->id, 'student_number' => 'LEGACY', 'email' => $user->email, 'first_name' => 'Existing', 'last_name' => 'Student']);
        $period = ReviewPeriod::create(['academic_year' => '2025-2026', 'semester' => '2nd Semester', 'start_date' => '2025-12-01', 'end_date' => '2026-05-31']);
        $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $period->id, 'office_id' => $office->id, 'evaluatee_type' => 'office', 'submitted_at' => now(), 'comments' => 'Preserve historical feedback']);
        $answer = $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => 4]);
        $history = [(array) DB::table('evaluations')->first(), (array) DB::table('evaluation_answers')->first()];
        $before = DB::table('criteria_categories')->where('id', $category->id)->first();
        $migration->up();
        $after = DB::table('criteria_categories')->where('id', $category->id)->first();
        foreach ((array) $before as $field => $value) $this->assertSame($value, $after->$field);
        $this->assertNull($after->office_id);
        $this->assertSame('Original question', $question->fresh()->text);
        $this->assertSame($history, [(array) DB::table('evaluations')->first(), (array) DB::table('evaluation_answers')->first()]);
        $duplicate = false;
        try { CriteriaCategory::create(['evaluatee_type' => 'office', 'name' => 'Duplicate shared', 'section_number' => 1]); }
        catch (\Illuminate\Database\UniqueConstraintViolationException) { $duplicate = true; }
        $this->assertTrue($duplicate, 'Shared NULL ownership must retain unique sections');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('criteria_categories', 'office_id'));
        $this->assertSame('Original question', $question->fresh()->text);
        $migration->up();
        $this->assertSame($history, [(array) DB::table('evaluations')->first(), (array) DB::table('evaluation_answers')->first()]);
    }

    public function test_office_uniqueness_foreign_keys_and_non_destructive_rollback_guard(): void
    {
        $offices = [Office::create(['code' => 'A', 'name' => 'A']), Office::create(['code' => 'B', 'name' => 'B'])];
        foreach ($offices as $office) CriteriaCategory::create(['evaluatee_type' => 'office', 'office_id' => $office->id, 'name' => 'Private', 'section_number' => 1]);
        $duplicate = false;
        try { CriteriaCategory::create(['evaluatee_type' => 'office', 'office_id' => $offices[0]->id, 'name' => 'Duplicate private', 'section_number' => 1]); }
        catch (\Illuminate\Database\UniqueConstraintViolationException) { $duplicate = true; }
        $this->assertTrue($duplicate);
        $restricted = false;
        try { $offices[0]->delete(); } catch (\Illuminate\Database\QueryException) { $restricted = true; }
        $this->assertTrue($restricted);
        $invalid = false;
        try { CriteriaCategory::create(['evaluatee_type' => 'office', 'office_id' => 999999, 'name' => 'Invalid', 'section_number' => 1]); }
        catch (\Illuminate\Database\QueryException) { $invalid = true; }
        $this->assertTrue($invalid);
        $migration = require database_path('migrations/2026_11_01_000001_add_office_scope_to_criteria_categories.php');
        try { $migration->down();$this->fail('Rollback must not discard private ownership'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Office-specific criteria exist', $e->getMessage()); }
        $this->assertDatabaseCount('criteria_categories', 2);
        $this->assertTrue(Schema::hasColumn('criteria_categories', 'office_id'));
    }
}
