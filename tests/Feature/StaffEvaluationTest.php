<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, Office, OfficeStaff, Program, ReviewPeriod, StaffCriteriaCategory, StaffCriteriaQuestion, StaffEvaluation, StaffEvaluationAnswer, Student, User};
use App\Services\{ReportService, StaffReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Illuminate\Database\QueryException;
use Tests\TestCase;

class StaffEvaluationTest extends TestCase
{
    use RefreshDatabase;
    protected Office $office;
    protected Office $other;
    protected OfficeStaff $member;
    protected ReviewPeriod $period;
    protected Program $program;
    protected array $questions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $department = Department::create(['code' => 'SYN', 'name' => 'Synthetic Department']);
        $this->program = $department->programs()->create(['code' => 'SYN', 'name' => 'Synthetic Program']);
        $foreign = Department::create(['code' => 'OTH', 'name' => 'Other Department']);
        $this->office = Office::create(['code' => 'REG', 'name' => 'Synthetic Registrar']);
        $this->other = Office::create(['code' => 'OTH', 'name' => 'Foreign Office', 'department_id' => $foreign->id]);
        $this->member = $this->office->staff()->create(['name' => 'Synthetic Staff', 'position' => 'Records Assistant']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => now()->subMonth(), 'end_date' => now()->subDay(), 'is_active' => true, 'is_submission_open' => true]);
        $this->questions = [];
        foreach ([null, $this->office->id, $this->other->id] as $i => $officeId) {
            $category = StaffCriteriaCategory::create(['office_id' => $officeId, 'name' => 'Staff Category '.$i, 'section_number' => $i + 1]);
            $this->questions[] = $category->questions()->create(['text' => 'Exact staff wording '.$i, 'order' => 1]);
        }
    }

    protected function student(): Student
    {
        $user = User::factory()->create(['role' => 'student']);
        return $this->program->students()->create(['user_id' => $user->id, 'student_number' => 'S'.$user->id, 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Student']);
    }

    protected function submit(?Student $student = null, ?OfficeStaff $member = null, ?array $payload = null)
    {
        return $this->actingAs(($student ?? $this->student())->user)->post(route('evaluation.staff.store', $member ?? $this->member), $payload ?? ['ratings' => [$this->questions[0]->id => 4, $this->questions[1]->id => 2], 'comments' => 'SECRET identifiable free text']);
    }

    protected function rows(): array
    {
        return app(StaffReportService::class)->rows($this->office->id, $this->period->id, null)->all();
    }


    public function test_optional_staff_feedback_enforces_300_characters_and_keeps_reports_private(): void
    {
        $ratings = [$this->questions[0]->id => 4, $this->questions[1]->id => 2];
        $student = $this->student();
        $this->submit($student, payload: ['ratings' => $ratings, 'comments' => str_repeat('a', 301)])
            ->assertSessionHasErrors('comments');
        $this->assertDatabaseCount('staff_evaluations', 0);
        $this->submit($student, payload: ['ratings' => $ratings, 'comments' => str_repeat('a', 300)])
            ->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('a', 300), StaffEvaluation::firstOrFail()->comments);
        $this->submit(payload: ['ratings' => $ratings, 'comments' => ''])->assertSessionHasNoErrors();
        $this->assertNull(StaffEvaluation::orderByDesc('id')->firstOrFail()->comments);
        $this->period->update(['is_submission_open' => false]);
        $report = json_encode($this->rows());
        $this->assertStringNotContainsString(str_repeat('a', 300), $report);
        $this->assertStringNotContainsString('comments', $report);
    }

    public function test_eligible_active_staff_and_optional_progress_are_separate_from_office_totals(): void
    {
        $student = $this->student();
        $own = Office::create(['code' => 'OWN', 'name' => 'Own office', 'department_id' => $this->program->department_id]);
        $ownMember = $own->staff()->create(['name' => 'Own Staff', 'position' => 'Assistant']);
        $foreign = $this->other->staff()->create(['name' => 'Foreign Staff', 'position' => 'Assistant']);
        $archived = $this->office->staff()->create(['name' => 'Archived Staff', 'position' => 'Assistant']);
        $archived->is_active = false; $archived->save();
        $props = $this->actingAs($student->user)->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
        $this->assertSame([$this->member->id, $ownMember->id], collect($props['officeStaff']['data'])->pluck('id')->sort()->values()->all());
        $this->assertSame([$this->questions[0]->id, $this->questions[1]->id], collect($props['officeStaff']['data'][0]['categories'])->flatMap(fn ($c) => collect($c['questions'])->pluck('id'))->all());
        $before = $props['progress'];
        $this->submit($student)->assertSessionHasNoErrors(); // Date expiry does not close submissions.
        $props = $this->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
        $this->assertSame($before, $props['progress']);
        $this->assertSame(['completed' => 1, 'total' => 2], $props['staffProgress']);
        $this->assertDatabaseCount('evaluations', 0);
        $this->assertDatabaseCount('evaluation_answers', 0);
        $this->submit($student, $foreign)->assertForbidden();
        $this->submit($student, $archived)->assertSessionHasErrors('staff');
        $this->submit($student, $ownMember, ['ratings' => [$this->questions[0]->id => 3]])->assertSessionHasNoErrors();
        foreach (['super_admin', 'office_admin', 'department_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('evaluation.staff.store', $this->member), [])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'student']))->post(route('evaluation.staff.store', $this->member), [])->assertForbidden();
    }

    public function test_exact_question_set_rating_bounds_period_gate_and_duplicate_backstop(): void
    {
        $student = $this->student();
        foreach ([[], ['ratings' => [$this->questions[0]->id => 4]], ['ratings' => [$this->questions[0]->id => 0, $this->questions[1]->id => 5]], ['ratings' => [$this->questions[0]->id => 3.5, $this->questions[1]->id => 2]], ['ratings' => [$this->questions[0]->id => 4, $this->questions[2]->id => 2]], ['ratings' => ['0'.$this->questions[0]->id => 4, $this->questions[1]->id => 2]] ] as $payload) {
            $this->submit($student, payload: $payload)->assertSessionHasErrors();
            $this->assertDatabaseCount('staff_evaluations', 0);
        }
        $this->period->update(['is_submission_open' => false]);
        $this->submit($student)->assertSessionHasErrors('staff');
        $this->period->update(['is_submission_open' => true]);
        $this->submit($student)->assertSessionHasNoErrors();
        $this->submit($student)->assertSessionHasErrors('staff');
        $this->assertDatabaseCount('staff_evaluations', 1);
        $this->assertDatabaseCount('staff_evaluation_answers', 2);
        $record = DB::table('staff_evaluations')->first();
        $copy = (array) $record; unset($copy['id']);
        try { DB::table('staff_evaluations')->insert($copy); $this->fail('Unique index must prevent concurrent duplicate inserts'); }
        catch (QueryException) { $this->assertDatabaseCount('staff_evaluations', 1); }
        $answer = (array) DB::table('staff_evaluation_answers')->first(); unset($answer['id']);
        try { DB::table('staff_evaluation_answers')->insert($answer); $this->fail('Duplicate question must be rejected'); }
        catch (QueryException) { $this->assertDatabaseCount('staff_evaluation_answers', 2); }
        $this->period->update(['is_active' => false]);
        $this->submit()->assertSessionHasErrors('staff');
    }

    public function test_submission_rolls_back_header_answers_and_freeze_on_failure(): void
    {
        $events = StaffEvaluationAnswer::getEventDispatcher();
        $isolated = clone $events;
        StaffEvaluationAnswer::setEventDispatcher($isolated);
        StaffEvaluationAnswer::creating(function () { throw new \RuntimeException('Synthetic storage failure'); });
        $this->withoutExceptionHandling();
        try { $this->submit(); $this->fail('Synthetic failure must propagate'); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic storage failure', $e->getMessage()); }
        finally { StaffEvaluationAnswer::setEventDispatcher($events); }
        $this->assertDatabaseCount('staff_evaluations', 0);
        $this->assertDatabaseCount('staff_evaluation_answers', 0);
        $this->assertNull($this->questions[0]->fresh()->locked_at);
        $this->assertNull($this->questions[0]->category->fresh()->locked_at);
    }

    public function test_snapshots_history_freezes_foreign_keys_and_rollback_guard(): void
    {
        $student = $this->student();
        $this->submit($student)->assertSessionHasNoErrors();
        $header = StaffEvaluation::firstOrFail();
        $this->assertSame('Records Assistant', $header->staff_position);
        $answer = $header->answers()->orderBy('id')->firstOrFail();
        $this->assertSame('Exact staff wording 0', $answer->question_text);
        $this->assertSame('Staff Category 0', $answer->category_name);
        $this->assertNotNull($this->questions[0]->fresh()->locked_at);
        $this->assertNotNull($this->questions[0]->category->fresh()->locked_at);
        $this->member->update(['name' => 'Renamed Staff', 'position' => 'Senior Officer']);
        $this->member->is_active = false; $this->member->save();
        $this->office->update(['name' => 'Renamed Office']);
        $this->assertSame('Synthetic Staff', $header->fresh()->staff_name);
        $this->assertSame('Records Assistant', $header->fresh()->staff_position);
        $this->assertSame('Synthetic Registrar', $header->fresh()->office_name);
        foreach (['office_staff' => $this->member->id, 'staff_criteria_questions' => $answer->staff_criteria_question_id, 'staff_criteria_categories' => $answer->staff_criteria_category_id, 'students' => $student->id, 'review_periods' => $this->period->id] as $table => $id) {
            try { DB::table($table)->where('id', $id)->delete(); $this->fail('Restrictive reference required: '.$table); }
            catch (QueryException) { $this->assertDatabaseHas($table, ['id' => $id]); }
        }
        try { DB::table('office_staff')->where('id', $this->member->id)->update(['office_id' => $this->other->id]); $this->fail('Historical ownership cannot move'); }
        catch (QueryException) { $this->assertSame($this->office->id, $this->member->fresh()->office_id); }
        $migration = require database_path('migrations/2026_11_19_000002_create_staff_evaluation_tables.php');
        try { $migration->down(); $this->fail('Populated rollback must refuse'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('history exists', $e->getMessage()); }
        $this->assertDatabaseCount('staff_evaluations', 1);
        $this->assertTrue(Schema::hasTable('staff_evaluation_answers'));
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->put(route('review-periods.update', $this->period), ['academic_year' => '2027-2028', 'semester' => '2nd Semester', 'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonth()->toDateString()])->assertSessionHasErrors();
        $this->assertSame('2026-2027', $this->period->fresh()->academic_year);
        $this->delete(route('review-periods.destroy', $this->period))->assertSessionHasErrors('period');
        $this->delete(route('programs.students.destroy', [$this->program->department_id, $this->program, $student]))->assertSessionHasErrors('student');
        $this->assertDatabaseCount('staff_evaluations', 1);
    }

    public function test_privacy_threshold_closed_flag_reopening_and_snapshot_search_cannot_slice_cohorts(): void
    {
        for ($i = 0; $i < 4; $i++) $this->submit()->assertSessionHasNoErrors();
        $this->period->update(['is_submission_open' => false]);
        $row = $this->rows()[0];
        $this->assertNull($row['rating']); $this->assertNull($row['responses']); $this->assertSame([], $row['sections']);
        $this->assertSame(StaffReportService::INSUFFICIENT, $row['disclosureMessage']);
        $this->period->update(['is_submission_open' => true]);
        $this->member->update(['position' => 'Senior Officer']);
        $this->submit()->assertSessionHasNoErrors();
        $this->assertNull($this->rows()[0]['rating']); // Even expired dates and five answers do not release.
        $this->period->update(['is_submission_open' => false]);
        $row = $this->rows()[0];
        $this->assertTrue($row['released']); $this->assertSame(5, $row['responses']);
        $this->assertSame(3.0, $row['rating']);
        $this->assertSame([4.0, 2.0], array_column($row['sections'], 'rating'));
        $this->assertSame('Records Assistant / Senior Officer', $row['position']);
        $searched = app(StaffReportService::class)->rows($this->office->id, $this->period->id, 'Records Assistant')->first();
        $this->assertEquals($row, $searched);
        $this->member->is_active = false; $this->member->save();
        $this->assertSame(5, $this->rows()[0]['responses']);
        $json = json_encode($row);
        foreach (['student_id', 'submitted_at', 'created_at', 'SECRET', 'question_text', 'comments', 'staff_evaluation_id'] as $secret) $this->assertStringNotContainsString($secret, $json);
        $this->period->update(['is_submission_open' => true]);
        $this->assertNull($this->rows()[0]['rating']);
        $this->assertSame(StaffReportService::OPEN, $this->rows()[0]['disclosureMessage']);
    }

    public function test_periods_do_not_pool_and_small_section_cohorts_are_suppressed(): void
    {
        for ($i = 0; $i < 4; $i++) $this->submit()->assertSessionHasNoErrors();
        $old = $this->period;
        $old->update(['is_active' => false, 'is_submission_open' => false]);
        $this->period = ReviewPeriod::create(['academic_year' => '2027-2028', 'semester' => '2nd Semester', 'start_date' => now(), 'end_date' => now()->addMonth(), 'is_active' => true, 'is_submission_open' => true]);
        $this->submit()->assertSessionHasNoErrors();
        $this->period->update(['is_submission_open' => false]);
        $rows = app(StaffReportService::class)->rows($this->office->id, null, null);
        $this->assertCount(2, $rows); $this->assertTrue($rows->every(fn ($r) => $r['rating'] === null && $r['responses'] === null));
        // A new section added in a later opening must not disclose a one-student section.
        $this->period = $old;
        $old->update(['is_active' => true, 'is_submission_open' => true]);
        $category = StaffCriteriaCategory::create(['name' => 'Later section', 'section_number' => 8]);
        $question = $category->questions()->create(['text' => 'New criterion', 'order' => 1]);
        $this->submit(payload: ['ratings' => [$this->questions[0]->id => 4, $this->questions[1]->id => 2, $question->id => 3]])->assertSessionHasNoErrors();
        $old->update(['is_submission_open' => false]);
        $this->assertNull($this->rows()[0]['rating']);
        $this->assertSame([], $this->rows()[0]['sections']);
    }

    public function test_staff_changes_leave_existing_office_reports_and_tables_identical(): void
    {
        $student = $this->student();
        $cat = CriteriaCategory::create(['evaluatee_type' => 'office', 'section_number' => 1, 'name' => 'Legacy office']);
        $q = $cat->questions()->create(['text' => 'Existing question', 'order' => 1]);
        $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id, 'evaluatee_type' => 'office', 'office_id' => $this->office->id, 'submitted_at' => now(), 'comments' => 'Existing feedback']);
        $evaluation->answers()->create(['criteria_question_id' => $q->id, 'rating' => 4]);
        $tables = ['evaluations', 'evaluation_answers', 'criteria_categories', 'criteria_questions', 'class_subjects', 'student_subject_enrollments'];
        $before = []; foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $report = app(ReportService::class)->officeReport(null, $this->period->id, 1, $this->office->id);
        $this->submit($student)->assertSessionHasNoErrors();
        Cache::flush();
        $this->assertEquals($report, app(ReportService::class)->officeReport(null, $this->period->id, 1, $this->office->id));
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
    }
    public function test_missing_criteria_forged_snapshots_and_immutable_records(): void
    {
        $student = $this->student();
        $this->submit($student, payload: ['ratings' => [$this->questions[0]->id => 4, $this->questions[1]->id => 2],
            'staff_name' => 'FORGED', 'staff_position' => 'FORGED', 'office_id' => $this->other->id])->assertSessionHasNoErrors();
        $evaluation = StaffEvaluation::firstOrFail();
        $this->assertSame('Synthetic Staff', $evaluation->staff_name);
        $this->assertSame($this->office->id, $evaluation->office_id);
        foreach ([$evaluation, $evaluation->answers()->firstOrFail()] as $record) {
            try { $record->delete(); $this->fail('History cannot be deleted through models'); }
            catch (\LogicException) { $this->assertTrue($record->exists); }
            $record->updated_at = now()->addDay();
            try { $record->save(); $this->fail('History cannot be rewritten through models'); }
            catch (\LogicException) { $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]); }
        }
        // An office with no shared/private questions has no evaluable form.
        $this->period->update(['is_submission_open' => false]);
        DB::table('staff_evaluation_answers')->delete(); DB::table('staff_evaluations')->delete();
        DB::table('staff_criteria_questions')->delete();
        $this->period->update(['is_submission_open' => true]);
        $this->submit()->assertSessionHasErrors('staff');
        $this->assertDatabaseCount('staff_evaluations', 0);
    }

}
