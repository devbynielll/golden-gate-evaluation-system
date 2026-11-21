<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Evaluation, Faculty, Office, ReviewPeriod, User};
use App\Services\ReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

class ReportImprovementsTest extends Phase1ARegressionTest
{
    public function refreshDatabase(): void
    {
        parent::refreshDatabase();
        // Disposable in-memory report fixtures only; no migrations or existing databases.
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('office_id')->nullable());
        Schema::table('class_subjects', fn (Blueprint $t) => $t->unsignedBigInteger('faculty_id')->nullable());
        Schema::create('departments', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->timestamps(); });
        Schema::create('programs', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('department_id'); $t->string('code'); $t->string('name'); $t->timestamps(); });
        Schema::create('faculty', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('program_id'); $t->string('faculty_id'); $t->string('name'); $t->timestamps(); });
        Schema::create('offices', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('department_id')->nullable(); $t->string('code'); $t->string('name'); $t->timestamps(); });
        Schema::create('criteria_categories', function (Blueprint $t) { $t->id(); $t->string('evaluatee_type'); $t->integer('section_number'); $t->string('name'); $t->unsignedBigInteger('office_id')->nullable(); $t->timestamps(); });
        Schema::create('criteria_questions', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('criteria_category_id'); $t->string('text'); $t->integer('order'); $t->timestamps(); });
        Schema::create('evaluations', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('review_period_id'); $t->unsignedBigInteger('student_id'); $t->string('evaluatee_type'); $t->unsignedBigInteger('class_subject_id')->nullable(); $t->unsignedBigInteger('office_id')->nullable(); $t->text('comments')->nullable(); $t->timestamp('submitted_at')->nullable(); $t->timestamps(); });
        Schema::create('evaluation_answers', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('evaluation_id'); $t->unsignedBigInteger('criteria_question_id'); $t->integer('rating'); $t->timestamps(); });
    }

    private function fixtures(): array
    {
        DB::table('departments')->insert([['id' => 1, 'code' => 'D1', 'name' => 'Synthetic A'], ['id' => 2, 'code' => 'D2', 'name' => 'Synthetic B']]);
        DB::table('programs')->insert([['id' => 1, 'department_id' => 1, 'code' => 'P1', 'name' => 'Synthetic A'], ['id' => 2, 'department_id' => 2, 'code' => 'P2', 'name' => 'Synthetic B']]);
        $period = ReviewPeriod::create(['academic_year' => '2026 - 2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31']);
        $faculty = Faculty::create(['program_id' => 1, 'faculty_id' => 'F001', 'name' => 'Synthetic Faculty']);
        $office = Office::create(['code' => 'O001', 'name' => 'Synthetic Office']);
        DB::table('class_subjects')->insert(['id' => 1, 'review_period_id' => $period->id, 'faculty_id' => $faculty->id]);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        return [$period, $faculty, $office];
    }

    private function category(string $type, int $section, string $name, ?int $officeId = null): CriteriaCategory
    {
        return CriteriaCategory::create(['evaluatee_type' => $type, 'section_number' => $section, 'name' => $name, 'office_id' => $officeId]);
    }

    private function evaluation($period, string $type, int $target, array $ratings, bool $submitted = true, string $feedback = 'Synthetic feedback'): Evaluation
    {
        $evaluation = Evaluation::create(['review_period_id' => $period->id, 'student_id' => 987654,
            'evaluatee_type' => $type, $type === 'faculty' ? 'class_subject_id' : 'office_id' => $target,
            'submitted_at' => $submitted ? '2026-11-01 12:00:00' : null, 'comments' => $feedback]);
        foreach ($ratings as $question => $rating) $evaluation->answers()->create(['criteria_question_id' => $question, 'rating' => $rating]);
        return $evaluation;
    }

    public function test_full_summaries_include_all_pages_and_server_applied_filter_labels(): void
    {
        [$period] = $this->fixtures();
        for ($i = 2; $i <= 13; $i++) {
            Faculty::create(['program_id' => $i === 13 ? 2 : 1, 'faculty_id' => 'F'.$i, 'name' => sprintf('Faculty %02d', $i)]);
            Office::create(['code' => 'O'.$i, 'name' => sprintf('Office %02d', $i)]);
        }
        foreach (['faculty', 'office'] as $type) {
            $page = $this->get(route('reports.'.$type))->assertOk()->viewData('page')['props'];
            $this->assertCount(10, $page[$type === 'faculty' ? 'faculty' : 'offices']['data']);
            $this->getJson(route('reports.'.$type, ['report' => 'summary', 'page' => 2, 'period_id' => $period->id]))
                ->assertOk()->assertJsonCount(13, 'summaryRows')->assertJsonPath('reviewPeriod', '1st Semester | 2026 - 2027');
        }
        $this->getJson(route('reports.faculty', ['report' => 'summary', 'department_id' => 1]))
            ->assertOk()->assertJsonCount(12, 'summaryRows')->assertJsonPath('filterLabel', 'Department: D1');
        $this->getJson(route('reports.office', ['report' => 'summary', 'search' => 'all']))
            ->assertOk()->assertJsonCount(0, 'summaryRows');
        $this->getJson(route('reports.office', ['report' => 'summary', 'search' => 'Synthetic']))
            ->assertOk()->assertJsonCount(1, 'summaryRows')->assertJsonPath('summaryRows.0.officeCode', 'O001');
    }

    public function test_faculty_sections_use_only_stored_answers_and_preserve_original_overall_and_feedback(): void
    {
        [$period, $faculty] = $this->fixtures();
        $a = $this->category('faculty', 1, 'Teaching');
        $b = $this->category('faculty', 2, 'Support');
        $this->category('faculty', 3, 'Unanswered category');
        $q1 = $a->questions()->create(['text' => 'Q1', 'order' => 1])->id;
        $q2 = $a->questions()->create(['text' => 'Q2', 'order' => 2])->id;
        $q3 = $b->questions()->create(['text' => 'Q3', 'order' => 1])->id;
        $this->evaluation($period, 'faculty', 1, [$q1 => 4, $q2 => 2, $q3 => 1]);
        $this->evaluation($period, 'faculty', 1, [$q1 => 4], feedback: str_repeat('x', 1100));
        $this->evaluation($period, 'faculty', 1, [$q1 => 1], false);
        $other = ReviewPeriod::create(['academic_year' => '2025-2026', 'semester' => 'Summer', 'start_date' => '2025-04-01', 'end_date' => '2025-06-01']);
        $this->evaluation($other, 'faculty', 1, [$q1 => 1]);
        $tables = ['evaluations', 'evaluation_answers', 'criteria_questions', 'criteria_categories', 'class_subjects', 'review_periods'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $baseline = app(ReportService::class)->facultyReport(null, $period->id, 1)['data']->first();
        $response = $this->getJson(route('reports.faculty', ['report' => 'individual', 'target_id' => $faculty->id, 'period_id' => $period->id]))->assertOk();
        $detail = $response->json('detail');
        $this->assertEquals(2.75, $detail['rating']); // NOT the average of section means.
        $this->assertSame($baseline['rating'], (float) $detail['rating']);
        $this->assertSame($baseline['status'], $detail['status']);
        $this->assertSame($baseline['feedback'], $detail['feedback']);
        $this->assertSame(2, $detail['responses']);
        $this->assertCount(2, $detail['sections']);
        $this->assertEquals(3.33, $detail['sections'][0]['rating']);
        $this->assertSame(3, $detail['sections'][0]['answeredItems']);
        $this->assertEquals(1, $detail['sections'][1]['rating']);
        $this->assertStringNotContainsString('student_id', $response->getContent());
        $this->assertStringNotContainsString('submitted_at', $response->getContent());
        $this->getJson(route('reports.faculty', ['report' => 'individual', 'target_id' => $faculty->id]))->assertJsonPath('detail.rating', 2.4);
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
    }

    public function test_office_shared_and_private_sections_stay_distinct_and_ownership_is_fresh(): void
    {
        [$period, , $office] = $this->fixtures();
        $other = Office::create(['code' => 'OTHER', 'name' => 'Other confidential office']);
        $shared = $this->category('office', 1, 'Shared')->questions()->create(['text' => 'Shared Q', 'order' => 1])->id;
        $private = $this->category('office', 1, 'Private', $office->id)->questions()->create(['text' => 'Private Q', 'order' => 1])->id;
        $this->evaluation($period, 'office', $office->id, [$shared => 4, $private => 2]);
        $this->evaluation($period, 'office', $other->id, [$shared => 1], feedback: 'Other office confidential feedback');
        $admin = User::factory()->create(['role' => 'office_admin', 'office_id' => $office->id]);
        // Warm the global summary before exercising narrower access.
        $this->getJson(route('reports.office', ['report' => 'summary']))->assertJsonCount(2, 'summaryRows');
        $this->actingAs($admin);
        $response = $this->getJson(route('reports.office', ['report' => 'individual', 'target_id' => $office->id]))->assertOk();
        $response->assertJsonCount(2, 'detail.sections')->assertJsonPath('detail.sections.0.rating', 4)->assertJsonPath('detail.sections.1.rating', 2);
        $this->assertStringContainsString('(Office-specific)', $response->json('detail.sections.1.label'));
        $this->assertStringNotContainsString('Other office confidential', $response->getContent());
        $this->getJson(route('reports.office', ['report' => 'summary']))->assertJsonCount(1, 'summaryRows');
        foreach (['target_id', 'office_id'] as $key) $this->getJson(route('reports.office', ['report' => 'individual', 'target_id' => $office->id, $key => $other->id]))->assertForbidden();
        $admin->update(['office_id' => $other->id]);
        $this->getJson(route('reports.office', ['report' => 'summary']))->assertJsonPath('summaryRows.0.id', $other->id);
        $admin->update(['office_id' => null]);
        $this->getJson(route('reports.office', ['report' => 'summary']))->assertForbidden();
    }

    public function test_all_report_modes_preserve_the_existing_role_boundaries(): void
    {
        [, $faculty, $office] = $this->fixtures();
        foreach (['super_admin', 'department_admin', 'office_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'office_id' => $role === 'office_admin' ? $office->id : null]));
            foreach (['faculty' => $faculty, 'office' => $office] as $type => $target) {
                foreach (['summary', 'individual'] as $mode) {
                    $response = $this->getJson(route('reports.'.$type, ['report' => $mode, 'target_id' => $target->id]));
                    $allowed = $role === 'super_admin' || ($role === 'department_admin' && $type === 'faculty') || ($role === 'office_admin' && $type === 'office');
                    $response->assertStatus($allowed ? 200 : 403);
                }
            }
        }
        auth()->logout();
        $this->getJson(route('reports.faculty', ['report' => 'summary']))->assertUnauthorized();
    }

    public function test_empty_missing_invalid_and_unanswered_reports_do_not_invent_ratings(): void
    {
        [, $faculty] = $this->fixtures();
        $this->getJson(route('reports.faculty', ['report' => 'individual', 'target_id' => $faculty->id]))
            ->assertOk()->assertJsonPath('detail.sections', [])->assertJsonPath('detail.status', 'No Data');
        $this->getJson(route('reports.office', ['report' => 'summary', 'search' => 'No match']))->assertOk()->assertJsonPath('summaryRows', []);
        $this->getJson(route('reports.faculty', ['report' => 'individual', 'target_id' => 999]))->assertNotFound();
        $this->getJson(route('reports.faculty', ['report' => 'individual']))->assertUnprocessable();
        $this->getJson(route('reports.faculty', ['report' => 'summary', 'period_id' => 999]))->assertUnprocessable();
    }
}
