<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, StaffCriteriaCategory, StaffCriteriaQuestion, User};
use App\Services\{ReportService, StudentEvaluationService};
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Validation\ValidationException;

// Also runs the inherited overall-Office questionnaire/ownership regression tests.
class StaffCriteriaManagementTest extends OfficeCriteriaManagementTest
{
    private Office $staffOffice;
    private Office $staffOther;
    private User $staffAdmin;
    private array $staffCategories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->staffOffice = Office::where('code', 'REG')->firstOrFail();
        $this->staffOther = Office::where('code', 'LIB')->firstOrFail();
        $this->staffAdmin = User::where('role', 'office_admin')->firstOrFail();
        foreach (['shared' => null, 'own' => $this->staffOffice->id, 'foreign' => $this->staffOther->id] as $scope => $officeId) {
            $category = StaffCriteriaCategory::create(['office_id' => $officeId, 'section_number' => 1, 'name' => 'Staff '.$scope]);
            $category->questions()->create(['text' => 'Staff '.$scope.' question', 'order' => 1]);
            $this->staffCategories[$scope] = $category;
        }
    }

    private function staffUrl(string $scope, bool $question = false): string
    {
        $category = $this->staffCategories[$scope];
        return '/admin/staff-criteria/categories/'.$category->id.($question ? '/questions/'.$category->questions()->firstOrFail()->id : '');
    }

    public function test_staff_boards_show_only_shared_and_own_scopes_and_super_selects_explicit_scope(): void
    {
        $props = $this->get(route('criteria.staff'))->assertOk()->viewData('page')['props'];
        $this->assertSame('staff', $props['criteriaType']);
        $this->assertSame($this->staffOffice->id, $props['selectedOfficeId']);
        $this->assertCount(2, $props['categories']['data']);
        $this->assertContains($this->staffCategories['shared']->id, $props['criteriaPermissions']['readOnlyCategoryIds']);
        $this->assertStringNotContainsString('Staff foreign', json_encode($props));
        $this->get(route('criteria.staff', ['office_id' => $this->staffOther->id]))->assertForbidden();
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail());
        $props = $this->get(route('criteria.staff'))->assertOk()->viewData('page')['props'];
        $this->assertCount(1, $props['categories']['data']);
        $props = $this->get(route('criteria.staff', ['office_id' => $this->staffOther->id]))->assertOk()->viewData('page')['props'];
        $this->assertSame('Staff foreign', $props['categories']['data'][0]['name']);
    }

    public function test_staff_category_question_crud_uses_server_owned_scope_type_and_order(): void
    {
        $oldCount = CriteriaCategory::count();
        foreach (['super_admin', 'office_admin'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail());
            $officeId = $role === 'office_admin' ? $this->staffOffice->id : null;
            $this->post(route('staff-criteria.categories.store'), ['office_id' => $officeId, 'section' => 2, 'name' => $role, 'evaluatee_type' => 'office', 'locked_at' => now()])->assertSessionHasNoErrors();
            $category = StaffCriteriaCategory::where('name', $role)->firstOrFail();
            $this->assertSame($officeId, $category->office_id);
            $this->assertNull($category->locked_at);
            $this->post(route('staff-criteria.questions.store', $category), ['text' => 'New staff question', 'order' => 100, 'criteria_category_id' => $this->staffCategories['foreign']->id])->assertSessionHasNoErrors();
            $question = $category->questions()->firstOrFail();
            $this->assertSame(1, (int) $question->order);
            $this->put(route('staff-criteria.categories.update', $category), ['name' => 'Corrected '.$role, 'section' => 2])->assertSessionHasNoErrors();
            $this->put(route('staff-criteria.questions.update', [$category, $question]), ['text' => 'Corrected question'])->assertSessionHasNoErrors();
            $this->assertSame('Corrected question', $question->fresh()->text);
            $this->delete(route('staff-criteria.questions.destroy', [$category, $question]))->assertSessionHasNoErrors();
            $this->delete(route('staff-criteria.categories.destroy', $category))->assertSessionHasNoErrors();
        }
        $this->assertSame($oldCount, CriteriaCategory::count());
        $this->assertDatabaseCount('staff_criteria_categories', 3);
    }

    public function test_shared_foreign_and_forged_staff_ids_are_protected_for_every_mutation(): void
    {
        foreach (['shared', 'foreign'] as $scope) {
            $this->put($this->staffUrl($scope), ['name' => 'Forged', 'section' => 1])->assertForbidden();
            $this->delete($this->staffUrl($scope))->assertForbidden();
            $this->post($this->staffUrl($scope).'/questions', ['text' => 'Forged'])->assertForbidden();
            $this->put($this->staffUrl($scope, true), ['text' => 'Forged'])->assertForbidden();
            $this->delete($this->staffUrl($scope, true))->assertForbidden();
        }
        $this->post(route('staff-criteria.categories.store'), ['office_id' => null, 'section' => 3, 'name' => 'Forged shared'])->assertForbidden();
        $foreignQuestion = $this->staffCategories['foreign']->questions()->firstOrFail();
        $this->put($this->staffUrl('own').'/questions/'.$foreignQuestion->id, ['text' => 'Forged'])->assertNotFound();
        $this->put($this->staffUrl('own'), ['name' => 'Forged move', 'section' => 1, 'office_id' => $this->staffOther->id])->assertForbidden();
        foreach (['department_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('criteria.staff'))->assertForbidden();
            $this->post(route('staff-criteria.categories.store'), ['name' => 'No', 'section' => 3])->assertForbidden();
            $this->put($this->staffUrl('own'), ['name' => 'No', 'section' => 1])->assertForbidden();
            $this->delete($this->staffUrl('own', true))->assertForbidden();
        }
        $this->actingAs($this->staffAdmin);
        $this->staffAdmin->update(['office_id' => null]);
        $this->get(route('criteria.staff'))->assertForbidden();
        $this->put($this->staffUrl('own', true), ['text' => 'No'])->assertForbidden();
    }

    public function test_history_freeze_is_atomic_idempotent_and_blocks_stale_model_and_http_edits_for_both_roles(): void
    {
        $category = $this->staffCategories['own'];
        $question = $category->questions()->firstOrFail();
        $staleQuestion = $question->fresh();
        $staleCategory = $category->fresh();
        try {
            DB::transaction(function () use ($question) { $question->freezeForHistory(); throw new \RuntimeException('Synthetic failed submission'); });
        } catch (\RuntimeException $e) { $this->assertSame('Synthetic failed submission', $e->getMessage()); }
        $this->assertNull($question->fresh()->locked_at);
        $this->get(route('criteria.staff'))->assertOk(); // Warm the board before freezing.
        $question->freezeForHistory();
        $lockedAt = $question->fresh()->locked_at->toISOString();
        $question->freezeForHistory();
        $this->assertSame($lockedAt, $question->fresh()->locked_at->toISOString());
        foreach (['office_admin', 'super_admin'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail());
            $this->put($this->staffUrl('own', true), ['text' => 'Rewrite'])->assertSessionHasErrors('criteria');
            $this->delete($this->staffUrl('own', true))->assertSessionHasErrors('criteria');
            $this->put($this->staffUrl('own'), ['name' => 'Rewrite', 'section' => 1])->assertSessionHasErrors('criteria');
            $this->delete($this->staffUrl('own'))->assertSessionHasErrors('criteria');
        }
        $staleQuestion->text = 'Stale rewrite';
        try { $staleQuestion->save(); $this->fail('A stale model must not bypass history protection'); } catch (ValidationException $e) { $this->assertArrayHasKey('criteria', $e->errors()); }
        $staleCategory->name = 'Stale category rewrite';
        try { $staleCategory->save(); $this->fail('A stale category must not bypass history protection'); } catch (ValidationException $e) { $this->assertArrayHasKey('criteria', $e->errors()); }
        $this->actingAs($this->staffAdmin);
        $props = $this->get(route('criteria.staff'))->assertOk()->viewData('page')['props'];
        $this->assertContains($question->id, $props['criteriaPermissions']['usedQuestionIds']);
        $this->assertContains($category->id, $props['criteriaPermissions']['usedCategoryIds']);
        $this->post($this->staffUrl('own').'/questions', ['text' => 'New unused question'])->assertSessionHasNoErrors();
        $this->assertSame('Staff own question', $question->fresh()->text);
    }

    public function test_staff_writes_lock_during_open_periods_and_duplicate_sections_respect_scope(): void
    {
        $this->post(route('staff-criteria.categories.store'), ['name' => 'Duplicate', 'section' => 1])->assertSessionHasErrors('section');
        $this->post(route('staff-criteria.categories.store'), ['name' => 'Invalid', 'section' => 0])->assertSessionHasErrors('section');
        ReviewPeriod::firstOrFail()->update(['is_submission_open' => true]);
        foreach (['super_admin', 'office_admin'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail());
            $this->post(route('staff-criteria.categories.store'), ['name' => 'Blocked', 'section' => 5])->assertSessionHasErrors('criteria');
            $this->post($this->staffUrl('own').'/questions', ['text' => 'Blocked'])->assertSessionHasErrors('criteria');
            $this->put($this->staffUrl('own', true), ['text' => 'Blocked'])->assertSessionHasErrors('criteria');
            $this->delete($this->staffUrl('own'))->assertSessionHasErrors('criteria');
        }
        $this->assertDatabaseCount('staff_criteria_categories', 3);
        $this->assertDatabaseCount('staff_criteria_questions', 3);
    }

    public function test_staff_maintenance_leaves_old_questionnaires_scores_feedback_and_records_identical(): void
    {
        $period = ReviewPeriod::firstOrFail();
        $department = Department::create(['code' => 'SAFE', 'name' => 'Synthetic preservation']);
        $program = $department->programs()->create(['code' => 'SAFE', 'name' => 'Synthetic preservation']);
        $user = User::factory()->create(['role' => 'student']);
        $student = $program->students()->create(['user_id' => $user->id, 'student_number' => 'SAFE001', 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Student']);
        $question = CriteriaCategory::whereNull('office_id')->firstOrFail()->questions()->firstOrFail();
        $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $period->id, 'evaluatee_type' => 'office', 'office_id' => $this->staffOffice->id, 'submitted_at' => now(), 'comments' => 'Historical combined feedback']);
        $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => 4]);
        $service = app(StudentEvaluationService::class);
        $beforeCriteria = $service->criteria('office', $this->staffOffice->id);
        $beforeReport = app(ReportService::class)->officeReport(null, $period->id, 1, $this->staffOffice->id);
        $tables = ['users', 'students', 'evaluations', 'evaluation_answers', 'criteria_categories', 'criteria_questions', 'class_subjects', 'student_subject_enrollments'];
        $before = []; foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $this->post($this->staffUrl('own').'/questions', ['text' => 'Staff-only new question'])->assertSessionHasNoErrors();
        $this->post(route('offices.staff.store', $this->staffOffice), ['name' => 'Synthetic employee', 'position' => 'Assistant'])->assertSessionHasNoErrors();
        $member = $this->staffOffice->staff()->firstOrFail();
        $this->patch(route('offices.staff.archive', [$this->staffOffice, $member]))->assertSessionHasNoErrors();
        Cache::flush();
        $this->assertEquals($beforeCriteria, $service->criteria('office', $this->staffOffice->id));
        $this->assertEquals($beforeReport, app(ReportService::class)->officeReport(null, $period->id, 1, $this->staffOffice->id));
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
    }
}
