<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, Subject, User};
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PostDefenseMigrationIntegrationTest extends TestCase
{
    public function test_baseline_data_and_schema_survive_ordered_additive_upgrade_and_empty_reverse_rollback(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertFalse(Schema::hasTable('users'));
        $newPaths = glob(database_path('migrations/2026_11_19_*.php'));
        $baselinePaths = array_values(array_diff(glob(database_path('migrations/*.php')), $newPaths));
        $this->artisan('migrate', ['--path' => $baselinePaths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('office_staff'));
        $department = Department::create(['code' => 'BASE', 'name' => 'Synthetic baseline department']);
        $program = $department->programs()->create(['code' => 'BASE', 'name' => 'Synthetic baseline program']);
        $user = User::factory()->create(['role' => 'student']);
        $student = $program->students()->create(['student_number' => 'BASE001', 'user_id' => $user->id, 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Baseline']);
        $office = Office::create(['code' => 'BASE', 'name' => 'Synthetic baseline office']);
        $period = ReviewPeriod::create(['academic_year' => '2025-2026', 'semester' => '1st Semester', 'start_date' => '2025-06-01', 'end_date' => '2025-10-01']);
        $section = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = Subject::create(['program_id' => $program->id, 'code' => 'BASE101', 'description' => 'Historical subject', 'year_level' => 1]);
        $faculty = $program->faculty()->create(['faculty_id' => 'BASE001', 'name' => 'Synthetic baseline faculty']);
        $offering = ClassSubject::create(['class_section_id' => $section->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id, 'review_period_id' => $period->id]);
        foreach (['faculty', 'office'] as $type) {
            $category = CriteriaCategory::create(['evaluatee_type' => $type, 'section_number' => 1, 'name' => 'Historical '.$type.' section']);
            $question = $category->questions()->create(['text' => 'Historical '.$type.' wording', 'order' => 1]);
            $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $period->id, 'evaluatee_type' => $type,
                'class_subject_id' => $type === 'faculty' ? $offering->id : null, 'office_id' => $type === 'office' ? $office->id : null,
                'comments' => 'Synthetic historical aggregate feedback', 'submitted_at' => now()]);
            $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => $type === 'faculty' ? 4 : 2]);
        }
        // Include every baseline application table, not just the populated sentinel tables.
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name"))->pluck('name');
        $rows = []; $ddl = [];
        foreach ($tables as $table) {
            $rows[$table] = DB::table($table)->get()->toJson();
            $ddl[$table] = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql;
        }
        $this->artisan('migrate', ['--path' => $newPaths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        foreach (['office_staff', 'staff_criteria_categories', 'staff_criteria_questions', 'staff_evaluations', 'staff_evaluation_answers', 'notifications'] as $table) {
            $this->assertTrue(Schema::hasTable($table)); $this->assertDatabaseCount($table, 0);
        }
        foreach ($tables as $table) {
            $this->assertSame($rows[$table], DB::table($table)->get()->toJson(), 'Upgrade altered baseline rows: '.$table);
            $this->assertSame($ddl[$table], DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql, 'Upgrade altered baseline schema: '.$table);
        }
        foreach (array_reverse($newPaths) as $path) (require $path)->down();
        foreach ($tables as $table) $this->assertSame($rows[$table], DB::table($table)->get()->toJson(), 'Empty rollback altered baseline rows: '.$table);
        $this->assertFalse(Schema::hasTable('office_staff')); $this->assertFalse(Schema::hasTable('notifications'));
        foreach ($newPaths as $path) (require $path)->up();
        $this->assertDatabaseCount('evaluations', 2); $this->assertDatabaseCount('evaluation_answers', 2);
        $this->assertSame([4, 2], DB::table('evaluation_answers')->orderBy('id')->pluck('rating')->all());
    }
}
