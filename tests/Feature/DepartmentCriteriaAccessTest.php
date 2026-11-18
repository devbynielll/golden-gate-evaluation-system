<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, CriteriaQuestion, Department, Evaluation, EvaluationAnswer, ReviewPeriod, User};
use App\Services\{ReportService, StudentEvaluationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DepartmentCriteriaAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private CriteriaCategory $category;
    private CriteriaCategory $office;
    private CriteriaQuestion $question;
    private CriteriaQuestion $officeQuestion;
    private ReviewPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->admin = User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]);
        $this->category = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'name' => 'Teaching', 'section_number' => 1]);
        $this->office = CriteriaCategory::create(['evaluatee_type' => 'office', 'name' => 'Service', 'section_number' => 1]);
        $this->question = $this->category->questions()->create(['text' => 'Explains clearly', 'order' => 1]);
        $this->officeQuestion = $this->office->questions()->create(['text' => 'Helpful service', 'order' => 1]);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'is_active' => true, 'is_submission_open' => false, 'start_date' => now()->subDay(), 'end_date' => now()->addMonth()]);
        $this->actingAs($this->admin);
    }

    private function categoryUrl(string $type = 'faculty', ?CriteriaCategory $category = null): string
    {
        return '/admin/criteria/'.$type.'/categories/'.($category ?? $this->category)->id;
    }

    private function questionUrl(string $type = 'faculty', ?CriteriaCategory $category = null, ?CriteriaQuestion $question = null): string
    {
        return $this->categoryUrl($type, $category).'/questions/'.($question ?? $this->question)->id;
    }

    private function fixture(): array
    {
        $department = Department::create(['code' => 'TEST', 'name' => 'Synthetic']);
        $program = $department->programs()->create(['code' => 'TEST', 'name' => 'Synthetic']);
        $faculty = $program->faculty()->create(['faculty_id' => 'F16', 'name' => 'Synthetic Teacher']);
        $subject = $program->subjects()->create(['code' => 'S16', 'description' => 'Synthetic', 'year_level' => 1]);
        $section = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $offering = ClassSubject::create(['class_section_id' => $section->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id, 'review_period_id' => $this->period->id]);
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = $program->students()->create(['user_id' => $user->id, 'student_number' => 'S16', 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Student', 'is_irregular' => false, 'class_section_id' => $section->id]);
        return [$student, $offering];
    }

    private function usedQuestion(bool $submitted = true): Evaluation
    {
        [$student, $offering] = $this->fixture();
        $evaluation = Evaluation::create(['review_period_id' => $this->period->id, 'student_id' => $student->id, 'class_subject_id' => $offering->id, 'evaluatee_type' => 'faculty', 'submitted_at' => $submitted ? now() : null]);
        $evaluation->answers()->create(['criteria_question_id' => $this->question->id, 'rating' => 4]);
        return $evaluation;
    }

    public function test_department_views_faculty_questions_and_scoped_permissions(): void
    {
        $props = $this->get(route('criteria.faculty'))->assertOk()->viewData('page')['props'];
        $this->assertSame('Admin/Criteria/Faculty', $this->get(route('criteria.faculty'))->viewData('page')['component']);
        $this->assertSame('Teaching', $props['categories']['data'][0]['name']);
        $this->assertSame('Explains clearly', $props['categories']['data'][0]['questions'][0]['text']);
        $this->assertSame(1, $props['totalQuestions']);
        $this->assertFalse($props['criteriaPermissions']['writesLocked']);
    }

    public function test_department_creates_category_and_question_with_server_owned_type_and_order(): void
    {
        $this->post('/admin/criteria/faculty/categories', ['name' => 'New faculty category', 'section' => 2, 'evaluatee_type' => 'office'])->assertRedirect();
        $category = CriteriaCategory::where('name', 'New faculty category')->firstOrFail();
        $this->assertSame('faculty', $category->evaluatee_type);
        $this->post($this->categoryUrl(category: $category).'/questions', ['text' => 'New question', 'order' => 999, 'criteria_category_id' => $this->office->id])->assertRedirect();
        $this->assertDatabaseHas('criteria_questions', ['criteria_category_id' => $category->id, 'text' => 'New question', 'order' => 1]);
    }

    public function test_unused_category_and_question_can_be_edited_without_moving_or_reweighting(): void
    {
        $this->put($this->categoryUrl(), ['name' => 'Updated teaching', 'section' => 2, 'evaluatee_type' => 'office'])->assertRedirect();
        $this->put($this->questionUrl(), ['text' => 'Updated unused question', 'criteria_category_id' => $this->office->id, 'order' => 999])->assertRedirect();
        $this->assertDatabaseHas('criteria_categories', ['id' => $this->category->id, 'name' => 'Updated teaching', 'section_number' => 2, 'evaluatee_type' => 'faculty']);
        $this->assertDatabaseHas('criteria_questions', ['id' => $this->question->id, 'text' => 'Updated unused question', 'order' => 1, 'criteria_category_id' => $this->category->id]);
    }

    public function test_department_cannot_view_or_write_office_criteria(): void
    {
        $this->get(route('criteria.office'))->assertForbidden();
        $this->post('/admin/criteria/office/categories', ['name' => 'Forbidden', 'section' => 2])->assertForbidden();
        $this->put($this->categoryUrl('office', $this->office), ['name' => 'Forbidden', 'section' => 2])->assertForbidden();
        $this->delete($this->categoryUrl('office', $this->office))->assertForbidden();
        $this->post($this->categoryUrl('office', $this->office).'/questions', ['text' => 'Forbidden'])->assertForbidden();
        $this->put($this->questionUrl('office', $this->office, $this->officeQuestion), ['text' => 'Forbidden'])->assertForbidden();
        $this->delete($this->questionUrl('office', $this->office, $this->officeQuestion))->assertForbidden();
        $this->assertDatabaseHas('criteria_questions', ['id' => $this->officeQuestion->id, 'text' => 'Helpful service']);
    }

    public function test_forged_office_category_ids_under_faculty_urls_are_rejected(): void
    {
        $url = $this->categoryUrl(category: $this->office);
        $this->put($url, ['name' => 'Forged', 'section' => 2])->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->post($url.'/questions', ['text' => 'Forged'])->assertNotFound();
        $this->put($this->questionUrl(category: $this->office, question: $this->officeQuestion), ['text' => 'Forged'])->assertNotFound();
        $this->delete($this->questionUrl(category: $this->office, question: $this->officeQuestion))->assertNotFound();
    }

    public function test_forged_question_ids_cannot_cross_categories_or_evaluatee_types(): void
    {
        $other = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'name' => 'Other', 'section_number' => 2]);
        $otherQuestion = $other->questions()->create(['text' => 'Other question', 'order' => 1]);
        foreach ([$otherQuestion, $this->officeQuestion] as $question) {
            $this->put($this->questionUrl(question: $question), ['text' => 'Forged'])->assertNotFound();
            $this->delete($this->questionUrl(question: $question))->assertNotFound();
        }
        $this->assertDatabaseCount('criteria_questions', 3);
    }

    public function test_used_questions_and_categories_cannot_be_updated_or_deleted_and_reports_stay_identical(): void
    {
        $evaluation = $this->usedQuestion();
        $this->period->update(['is_active' => false]); // Historical answers remain protected.
        $before = $evaluation->answers()->get()->toJson();
        $reportBefore = app(ReportService::class)->facultyReport(null, $this->period->id, 1);
        foreach (['put', 'delete'] as $method) {
            $this->{$method}($this->questionUrl(), ['text' => 'Rewritten'])->assertSessionHasErrors('criteria');
            $this->{$method}($this->categoryUrl(), ['name' => 'Rewritten', 'section' => 3])->assertSessionHasErrors('criteria');
        }
        $this->assertSame($before, $evaluation->answers()->get()->toJson());
        $this->assertSame('Explains clearly', $this->question->fresh()->text);
        $this->assertSame('Teaching', $this->category->fresh()->name);
        Cache::flush();
        $this->assertEquals($reportBefore, app(ReportService::class)->facultyReport(null, $this->period->id, 1));
    }

    public function test_answer_references_are_protected_even_without_a_submission_timestamp(): void
    {
        $this->usedQuestion(false);
        $this->put($this->questionUrl(), ['text' => 'Rewritten'])->assertSessionHasErrors('criteria');
        $this->delete($this->categoryUrl())->assertSessionHasErrors('criteria');
        $this->assertDatabaseCount('evaluation_answers', 1);
    }

    public function test_used_category_may_receive_a_new_question_when_submissions_are_closed(): void
    {
        $this->usedQuestion();
        $this->post($this->categoryUrl().'/questions', ['text' => 'Additional question'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('evaluation_answers', 1);
        $this->assertDatabaseHas('criteria_questions', ['text' => 'Additional question', 'order' => 2]);
    }

    public function test_all_department_writes_are_blocked_while_submissions_are_open(): void
    {
        $this->period->update(['is_submission_open' => true]);
        $calls = [['post', '/admin/criteria/faculty/categories', ['name' => 'New', 'section' => 2]], ['put', $this->categoryUrl(), ['name' => 'Edit', 'section' => 2]], ['delete', $this->categoryUrl(), []], ['post', $this->categoryUrl().'/questions', ['text' => 'New']], ['put', $this->questionUrl(), ['text' => 'Edit']], ['delete', $this->questionUrl(), []]];
        foreach ($calls as [$method, $url, $body]) {
            $this->{$method}($url, $body)->assertSessionHasErrors('criteria');
        }
        $props = $this->get(route('criteria.faculty'))->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['criteriaPermissions']['writesLocked']);
        $this->assertDatabaseCount('criteria_questions', 2);
        $this->assertSame('Explains clearly', $this->question->fresh()->text);
    }

    public function test_unused_question_and_category_can_be_deleted_when_submissions_are_closed(): void
    {
        $this->delete($this->questionUrl())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('criteria_questions', ['id' => $this->question->id]);
        $this->delete($this->categoryUrl())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('criteria_categories', ['id' => $this->category->id]);
        $this->assertDatabaseHas('criteria_questions', ['id' => $this->officeQuestion->id]);
    }

    public function test_super_admin_obeys_faculty_history_and_office_period_protections(): void
    {
        $this->usedQuestion();
        $this->period->update(['is_submission_open' => true]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        foreach (['criteria.faculty', 'criteria.office'] as $route) {
            $props = $this->get(route($route))->assertOk()->viewData('page')['props'];
            $this->assertTrue($props['criteriaPermissions']['writesLocked']);
        }
        $this->put($this->questionUrl(), ['text' => 'Blocked historical rewrite'])->assertSessionHasErrors('criteria');
        $this->assertSame('Explains clearly', $this->question->fresh()->text);
        $this->post('/admin/criteria/office/categories', ['name' => 'Office extra', 'section' => 2])->assertSessionHasErrors('criteria');
        $this->period->update(['is_submission_open' => false]);
        $this->post('/admin/criteria/office/categories', ['name' => 'Office extra', 'section' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->put($this->questionUrl('office', $this->office, $this->officeQuestion), ['text' => 'Office edit'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_office_admin_and_student_cannot_access_any_criteria_endpoint(): void
    {
        foreach ([User::ROLE_OFFICE_ADMIN, User::ROLE_STUDENT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['faculty', 'office'] as $type) {
                $category = $type === 'faculty' ? $this->category : $this->office;
                $question = $type === 'faculty' ? $this->question : $this->officeQuestion;
                $this->get('/admin/criteria/'.$type)->assertForbidden();
                $this->post('/admin/criteria/'.$type.'/categories', ['name' => 'No', 'section' => 2])->assertForbidden();
                $this->put($this->categoryUrl($type, $category), ['name' => 'No', 'section' => 2])->assertForbidden();
                $this->delete($this->categoryUrl($type, $category))->assertForbidden();
                $this->post($this->categoryUrl($type, $category).'/questions', ['text' => 'No'])->assertForbidden();
                $this->put($this->questionUrl($type, $category, $question), ['text' => 'No'])->assertForbidden();
                $this->delete($this->questionUrl($type, $category, $question))->assertForbidden();
            }
        }
    }

    public function test_department_does_not_gain_other_super_admin_privileges(): void
    {
        foreach (['accounts.index', 'offices.index', 'criteria.office'] as $name) $this->get(route($name))->assertForbidden();
        $this->post('/admin/review-periods', [])->assertForbidden();
        $this->post('/admin/departments', ['code' => 'NO', 'name' => 'No'])->assertForbidden();
    }

    public function test_new_questions_refresh_student_criteria_without_rewriting_prior_answers(): void
    {
        $evaluation = $this->usedQuestion();
        app(StudentEvaluationService::class)->criteria('faculty'); // Warm cache before adding.
        $this->post($this->categoryUrl().'/questions', ['text' => 'New future question'])->assertRedirect();
        $criteria = app(StudentEvaluationService::class)->criteria('faculty');
        $this->assertCount(2, $criteria[0]['questions']);
        $this->assertCount(1, $evaluation->answers()->get());
        $this->assertSame(4, $evaluation->answers()->first()->rating);
    }

    public function test_question_set_added_before_opening_remains_compatible_with_student_submission(): void
    {
        [$student, $offering] = $this->fixture();
        $this->post($this->categoryUrl().'/questions', ['text' => 'New before opening'])->assertRedirect();
        $questions = $this->category->questions()->pluck('id');
        $this->period->update(['is_submission_open' => true]);
        $this->actingAs($student->user)->post(route('evaluation.faculty.store', $offering), ['ratings' => $questions->mapWithKeys(fn ($id) => [$id => 4])->all()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('evaluations', 1);
        $this->assertDatabaseCount('evaluation_answers', 2);
        $this->assertSame(4.0, (float) EvaluationAnswer::avg('rating'));
    }

    public function test_paginated_boards_report_the_used_question_locks_for_the_requested_page(): void
    {
        $evaluation = $this->usedQuestion();
        for ($section = 2; $section <= 6; $section++) {
            $last = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => $section, 'name' => 'Section '.$section]);
        }
        $question = $last->questions()->create(['text' => 'Historical page two question', 'order' => 1]);
        $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => 3]);
        $props = $this->get(route('criteria.faculty', ['page' => 2]))->assertOk()->viewData('page')['props'];
        $this->assertSame(2, $props['categories']['current_page']);
        $this->assertSame($last->id, $props['categories']['data'][0]['id']);
        $this->assertContains($question->id, $props['criteriaPermissions']['usedQuestionIds']);
        $this->assertContains($last->id, $props['criteriaPermissions']['usedCategoryIds']);
        $this->assertNotContains($this->question->id, $props['criteriaPermissions']['usedQuestionIds']);
    }

    public function test_role_specific_locks_do_not_leak_through_shared_board_cache(): void
    {
        $this->usedQuestion();
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($super)->get(route('criteria.faculty'))->assertOk();
        $props = $this->actingAs($this->admin)->get(route('criteria.faculty'))->assertOk()->viewData('page')['props'];
        $this->assertContains($this->question->id, $props['criteriaPermissions']['usedQuestionIds']);
        $this->assertContains($this->category->id, $props['criteriaPermissions']['usedCategoryIds']);
        $props = $this->actingAs($super)->get(route('criteria.faculty'))->assertOk()->viewData('page')['props'];
        $this->assertContains($this->question->id, $props['criteriaPermissions']['usedQuestionIds']);
        $this->assertContains($this->category->id, $props['criteriaPermissions']['usedCategoryIds']);
    }
}
