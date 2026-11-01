<?php

namespace Tests\Feature;

use App\Models\{CriteriaCategory, Department, Evaluation, EvaluationAnswer, Office, ReviewPeriod, User};
use App\Services\{ReportService, StudentEvaluationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

class OfficeCriteriaManagementTest extends TestCase
{
    use RefreshDatabase;

    private Office $registrar;
    private Office $library;
    private User $admin;
    private User $super;
    private CriteriaCategory $shared;
    private CriteriaCategory $own;
    private CriteriaCategory $foreign;
    private ReviewPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->registrar = Office::create(['code' => 'REG', 'name' => 'Registrar']);
        $this->library = Office::create(['code' => 'LIB', 'name' => 'Library']);
        $this->admin = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->registrar->id]);
        $this->super = User::factory()->create(['role' => 'super_admin']);
        foreach (['shared' => null, 'own' => $this->registrar->id, 'foreign' => $this->library->id] as $field => $officeId) {
            $this->$field = CriteriaCategory::create(['evaluatee_type' => 'office', 'office_id' => $officeId, 'section_number' => 1, 'name' => $field]);
            $this->$field->questions()->create(['text' => $field.' question', 'order' => 1]);
        }
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(), 'is_active' => true, 'is_submission_open' => false]);
        $this->actingAs($this->admin);
    }

    private function url(CriteriaCategory $category, bool $question = false): string
    {
        return '/admin/criteria/office/categories/'.$category->id.($question ? '/questions/'.$category->questions()->firstOrFail()->id : '');
    }

    private function student(): \App\Models\Student
    {
        $number = 'ST'.User::count();
        $department = Department::firstOrCreate(['code' => 'TEST'], ['name' => 'Test']);
        $program = $department->programs()->firstOrCreate(['code' => 'TEST'], ['name' => 'Test']);
        $user = User::factory()->create(['role' => 'student']);
        return $program->students()->create(['user_id' => $user->id, 'student_number' => $number, 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Student', 'is_irregular' => true]);
    }

    private function ratings(Office $office, int $rating = 4): array
    {
        return CriteriaCategory::forQuestionnaire('office', $office->id)->with('questions')->get()->flatMap->questions->mapWithKeys(fn ($q) => [$q->id => $rating])->all();
    }

    public function test_assigned_board_contains_shared_and_owned_questions_but_no_other_office(): void
    {
        $props = $this->get('/admin/criteria/office')->assertOk()->viewData('page')['props'];
        $this->assertSame([$this->shared->id, $this->own->id], array_column($props['categories']['data'], 'id'));
        $this->assertSame(2, $props['totalQuestions']);
        $this->assertSame([$this->shared->id], $props['criteriaPermissions']['readOnlyCategoryIds']);
        $this->assertSame([], $props['criteriaOffices']);
        $this->assertSame($this->registrar->id, $props['selectedOfficeId']);
        $this->get('/admin/criteria/office?office_id='.$this->library->id)->assertForbidden();
        $this->get('/admin/criteria/faculty')->assertForbidden();
    }

    public function test_office_admin_can_manage_only_unused_owned_records(): void
    {
        $this->post('/admin/criteria/office/categories', ['name' => 'New', 'section' => 2])->assertSessionHasNoErrors();
        $new = CriteriaCategory::where('name', 'New')->firstOrFail();
        $this->assertSame($this->registrar->id, $new->office_id);
        $this->put($this->url($new), ['name' => 'Updated', 'section' => 3])->assertSessionHasNoErrors();
        $this->post($this->url($new).'/questions', ['text' => 'New question', 'criteria_category_id' => $this->foreign->id, 'order' => 99])->assertSessionHasNoErrors();
        $this->assertSame(1, $new->questions()->first()->order);
        $this->put($this->url($new, true), ['text' => 'Changed'])->assertSessionHasNoErrors();
        $this->assertSame('Changed', $new->questions()->first()->text);
        $this->delete($this->url($new, true))->assertSessionHasNoErrors();
        $this->delete($this->url($new))->assertSessionHasNoErrors();
        $this->assertNull($new->fresh());
    }

    public function test_shared_and_foreign_mutations_and_forged_ownership_are_forbidden(): void
    {
        foreach ([$this->shared, $this->foreign] as $category) {
            $this->putJson($this->url($category), ['name' => 'No', 'section' => 2])->assertForbidden();
            $this->deleteJson($this->url($category))->assertForbidden();
            $this->postJson($this->url($category).'/questions', ['text' => 'No'])->assertForbidden();
            $this->putJson($this->url($category, true), ['text' => 'No'])->assertForbidden();
            $this->deleteJson($this->url($category, true))->assertForbidden();
        }
        foreach ([$this->library->id, null] as $id) $this->postJson('/admin/criteria/office/categories', ['name' => 'No', 'section' => 2, 'office_id' => $id])->assertForbidden();
        $this->putJson($this->url($this->own), ['name' => 'No', 'section' => 2, 'office_id' => $this->library->id])->assertForbidden();
        $this->putJson($this->url($this->own).'/questions/'.$this->foreign->questions()->first()->id, ['text' => 'No'])->assertNotFound();
        $this->assertDatabaseCount('criteria_categories', 3);
        $this->assertDatabaseCount('criteria_questions', 3);
        $this->postJson('/admin/criteria/office/categories', ['name' => 'Invalid section', 'section' => 65536])->assertUnprocessable();
    }

    public function test_unassigned_accounts_and_freshly_removed_assignments_are_denied(): void
    {
        $this->get('/admin/criteria/office')->assertOk(); // warm board and account
        DB::table('users')->where('id', $this->admin->id)->update(['office_id' => null]);
        $this->get('/admin/criteria/office')->assertForbidden();
        $this->postJson('/admin/criteria/office/categories', ['name' => 'No', 'section' => 2])->assertForbidden();
        $this->putJson($this->url($this->own, true), ['text' => 'No'])->assertForbidden();
    }

    public function test_department_and_student_do_not_gain_office_criteria_rights(): void
    {
        foreach (['department_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/criteria/office')->assertForbidden();
            $this->postJson('/admin/criteria/office/categories', ['name' => 'No', 'section' => 2])->assertForbidden();
            $this->putJson($this->url($this->own, true), ['text' => 'No'])->assertForbidden();
        }
    }

    public function test_super_admin_selects_and_manages_shared_and_specific_scopes_without_moving_records(): void
    {
        $this->actingAs($this->super);
        $props = $this->get('/admin/criteria/office')->assertOk()->viewData('page')['props'];
        $this->assertSame([$this->shared->id], array_column($props['categories']['data'], 'id'));
        $props = $this->get('/admin/criteria/office?office_id='.$this->library->id)->assertOk()->viewData('page')['props'];
        $this->assertSame([$this->foreign->id], array_column($props['categories']['data'], 'id'));
        $this->get('/admin/criteria/faculty')->assertOk();
        foreach ([null, $this->library->id] as $id) $this->post('/admin/criteria/office/categories', ['name' => 'Super scope '.($id ?? 'shared'), 'section' => 2, 'office_id' => $id])->assertSessionHasNoErrors();
        $this->putJson($this->url($this->shared), ['name' => 'Moved', 'section' => 3, 'office_id' => $this->library->id])->assertForbidden();
        $this->put($this->url($this->foreign, true), ['text' => 'Super edited unused'])->assertSessionHasNoErrors();
    }

    public function test_open_period_blocks_all_office_criteria_writes_for_both_roles(): void
    {
        $this->period->update(['is_submission_open' => true]);
        foreach ([$this->admin, $this->super] as $user) {
            $this->actingAs($user);
            $this->postJson('/admin/criteria/office/categories', ['name' => 'No', 'section' => 2])->assertUnprocessable();
            $this->putJson($this->url($this->own), ['name' => 'No', 'section' => 2])->assertUnprocessable();
            $this->deleteJson($this->url($this->own))->assertUnprocessable();
            $this->postJson($this->url($this->own).'/questions', ['text' => 'No'])->assertUnprocessable();
            $this->putJson($this->url($this->own, true), ['text' => 'No'])->assertUnprocessable();
            $this->deleteJson($this->url($this->own, true))->assertUnprocessable();
        }
    }

    public function test_answered_office_questions_and_categories_are_immutable_even_for_super_admin(): void
    {
        $student = $this->student();
        foreach ([$this->shared, $this->own] as $category) {
            $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id, 'office_id' => $this->registrar->id, 'evaluatee_type' => 'office', 'submitted_at' => null]);
            $evaluation->answers()->create(['criteria_question_id' => $category->questions()->first()->id, 'rating' => 4]);
            foreach ([$this->super, $this->admin] as $user) {
                $this->actingAs($user);
                $expected = $category->office_id === null && $user->isOfficeAdmin() ? 403 : 422;
                $this->putJson($this->url($category, true), ['text' => 'No'])->assertStatus($expected);
                $this->deleteJson($this->url($category, true))->assertStatus($expected);
                $this->putJson($this->url($category), ['name' => 'No', 'section' => 2])->assertStatus($expected);
                $this->deleteJson($this->url($category))->assertStatus($expected);
            }
            $this->assertSame(4, $evaluation->answers()->first()->rating);
            $this->assertSame($category->name.' question', $category->questions()->first()->text);
            // Remove only the evaluation fixture before creating another one with the same unique target.
            if ($category === $this->shared) $evaluation->delete();
        }
    }

    public function test_questionnaires_and_warmed_caches_are_isolated_by_office_and_match_student_board(): void
    {
        $service = app(StudentEvaluationService::class);
        $own = collect($service->criteria('office', $this->registrar->id))->pluck('name')->all();
        $other = collect($service->criteria('office', $this->library->id))->pluck('name')->all();
        $this->assertSame(['shared', 'own'], $own);
        $this->assertSame(['shared', 'foreign'], $other);
        $this->assertSame(['shared'], collect($service->criteria('office'))->pluck('name')->all());
        $board = $service->board($this->student(), $this->period, 1, 1);
        foreach ($board['offices']['data'] as $office) $this->assertSame($office['id'] === $this->registrar->id ? $own : $other, array_column($office['categories'], 'name'));
        $this->post($this->url($this->own).'/questions', ['text' => 'Cache refresh'])->assertSessionHasNoErrors();
        $this->assertCount(2, $service->criteria('office', $this->registrar->id)[1]['questions']);
        $this->assertCount(1, $service->criteria('office', $this->library->id)[1]['questions']);
    }

    public function test_office_deletion_cannot_cascade_private_criteria_or_history(): void
    {
        $this->actingAs($this->super)->deleteJson('/admin/offices/'.$this->registrar->id)->assertUnprocessable();
        $this->assertNotNull($this->registrar->fresh());
        $this->assertNotNull($this->own->fresh());
        $this->assertDatabaseCount('criteria_questions', 3);
    }

    public function test_office_submission_accepts_one_through_four_and_preserves_exact_answers_and_repeat_protection(): void
    {
        $this->period->update(['is_submission_open' => true]);
        foreach ([1, 2, 3, 4] as $rating) {
            $student = $this->student();
            $this->actingAs($student->user)->post(route('evaluation.office.store', $this->registrar), ['ratings' => $this->ratings($this->registrar, $rating)])->assertSessionHasNoErrors();
            $evaluation = $student->evaluations()->firstOrFail();
            $this->assertSame([$rating, $rating], $evaluation->answers()->pluck('rating')->all());
            $this->post(route('evaluation.office.store', $this->registrar), ['ratings' => $this->ratings($this->registrar, 1)])->assertSessionHasNoErrors();
            $this->assertSame([$rating, $rating], $evaluation->answers()->pluck('rating')->all());
        }
        $this->assertDatabaseCount('evaluations', 4);
        $this->assertSame(2.5, (float) EvaluationAnswer::avg('rating'));
        $this->actingAs($this->admin)->get(route('reports.office'))->assertOk();
    }

    public function test_foreign_missing_extra_noncanonical_and_invalid_rating_answers_are_rejected_without_writes(): void
    {
        $this->period->update(['is_submission_open' => true]);
        $this->actingAs($this->student()->user);
        $valid = $this->ratings($this->registrar);
        $first = array_key_first($valid);
        $alias = $valid;unset($alias[$first]);$alias['0'.$first] = 4;
        foreach ([$this->ratings($this->library), [$first => 4], $valid + [999999 => 4], $alias, $this->ratings($this->registrar, 5), $this->ratings($this->registrar, 0)] as $ratings) {
            $this->postJson(route('evaluation.office.store', $this->registrar), ['ratings' => $ratings])->assertUnprocessable();
            $this->assertDatabaseCount('evaluations', 0);
            $this->assertDatabaseCount('evaluation_answers', 0);
        }
    }
}
