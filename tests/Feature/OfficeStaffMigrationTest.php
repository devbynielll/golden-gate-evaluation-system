<?php

namespace Tests\Feature;

use App\Models\{Office, StaffCriteriaCategory, User};
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class OfficeStaffMigrationTest extends TestCase
{
    use RefreshDatabase;
    private function migration() { return require database_path('migrations/2026_11_19_000001_create_office_staff_and_staff_criteria_tables.php'); }

    public function test_empty_rollback_and_reapplication_preserve_existing_source_tables_and_records(): void
    {
        $office = Office::create(['code' => 'LEGACY', 'name' => 'Synthetic legacy office']);
        $user = User::factory()->create(['role' => 'student']);
        $before = [DB::table('offices')->get()->toJson(), DB::table('users')->get()->toJson()];
        $phase4 = require database_path('migrations/2026_11_19_000002_create_staff_evaluation_tables.php');
        $phase4->down();
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('office_staff'));
        $this->assertTrue(Schema::hasTable('evaluations'));
        $this->migration()->up();
        $phase4->up();
        $this->assertSame($before, [DB::table('offices')->get()->toJson(), DB::table('users')->get()->toJson()]);
        $this->assertDatabaseCount('office_staff', 0);
        $this->assertTrue(Schema::hasTable('staff_criteria_questions'));
    }

    public function test_populated_rollback_is_refused_before_any_tables_are_dropped(): void
    {
        $office = Office::create(['code' => 'KEEP', 'name' => 'Synthetic retained office']);
        $member = $office->staff()->create(['name' => 'Synthetic retained staff', 'position' => 'Assistant']);
        try { $this->migration()->down(); $this->fail('Populated rollback must be refused'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Preserve them', $e->getMessage()); }
        foreach (['office_staff', 'staff_criteria_categories', 'staff_criteria_questions'] as $table) $this->assertTrue(Schema::hasTable($table));
        $this->assertDatabaseHas('office_staff', ['id' => $member->id]);
    }

    public function test_nullable_shared_scope_is_unique_and_future_references_can_restrict_raw_deletion(): void
    {
        $office = Office::create(['code' => 'REF', 'name' => 'Synthetic reference office']);
        $category = StaffCriteriaCategory::create(['name' => 'Shared staff criteria', 'section_number' => 1]);
        $private = StaffCriteriaCategory::create(['office_id' => $office->id, 'name' => 'Private staff criteria', 'section_number' => 1]);
        $question = $category->questions()->create(['text' => 'Synthetic question', 'order' => 1]);
        try { StaffCriteriaCategory::create(['name' => 'Duplicate shared', 'section_number' => 1]); $this->fail('Shared uniqueness must be enforced by the database'); }
        catch (QueryException) { $this->assertDatabaseCount('staff_criteria_categories', 2); }
        $member = $office->staff()->create(['name' => 'Synthetic reference staff', 'position' => 'Assistant']);
        Schema::create('_phase3_history_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('office_staff')->restrictOnDelete();
            $table->foreignId('question_id')->constrained('staff_criteria_questions')->restrictOnDelete();
        });
        $question->freezeForHistory();
        DB::table('_phase3_history_references')->insert(['staff_id' => $member->id, 'question_id' => $question->id]);
        $member->is_active = false; $member->save();
        foreach (['office_staff' => $member->id, 'staff_criteria_questions' => $question->id, 'offices' => $office->id] as $table => $id) {
            try { DB::table($table)->where('id', $id)->delete(); $this->fail('Foreign references must restrict deletion'); }
            catch (QueryException) { $this->assertDatabaseHas($table, ['id' => $id]); }
        }
        $this->assertDatabaseCount('_phase3_history_references', 1);
    }
}
