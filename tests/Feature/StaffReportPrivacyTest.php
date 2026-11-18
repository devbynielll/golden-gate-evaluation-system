<?php
namespace Tests\Feature;

use App\Models\{OfficeStaff, StaffEvaluation, User};
use Illuminate\Support\Facades\DB;

class StaffReportPrivacyTest extends StaffEvaluationTest
{
    private function url(array $extra = []): string
    {
        return route('reports.staff', ['period_id' => $this->period->id, ...$extra]);
    }

    public function test_both_admin_roles_get_identical_suppressed_pages_previews_and_complete_exports(): void
    {
        for ($i = 0; $i < 4; $i++) $this->submit()->assertSessionHasNoErrors();
        $this->period->update(['is_submission_open' => false]);
        $target = hash('sha256', $this->member->id.':'.$this->period->id);
        $super = User::factory()->create(['role' => 'super_admin']);
        $office = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->office->id]);
        foreach ([$super, $office] as $actor) {
            $this->actingAs($actor);
            $props = $this->get($this->url())->assertOk()->viewData('page')['props'];
            $this->assertNull($props['staff']['data'][0]['responses']);
            foreach (['summary', 'individual'] as $type) {
                $response = $this->getJson($this->url(['report' => $type, 'target_id' => $target]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
                $row = $type === 'individual' ? $response->json('detail') : $response->json('summaryRows.0');
                $this->assertNull($row['rating']); $this->assertNull($row['responses']); $this->assertSame([], $row['sections']);
                $this->assertSame('Insufficient responses to protect student anonymity.', $row['disclosureMessage']);
                foreach (['student_id', 'submitted_at', 'SECRET', 'comments', 'question_text', 'staff_evaluation_id'] as $secret) $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }
        $this->period->update(['is_submission_open' => true]);
        $this->submit()->assertSessionHasNoErrors();
        $this->period->update(['is_submission_open' => false]);
        $released = null;
        foreach ([$super, $office] as $actor) {
            $this->actingAs($actor);
            $detail = $this->getJson($this->url(['report' => 'individual', 'target_id' => $target]))->assertOk()->json('detail');
            $this->assertSame(5, $detail['responses']);
            $summary = $this->getJson($this->url(['report' => 'summary']))->assertOk()->json('summaryRows.0');
            $this->assertEquals($detail, $summary);
            if ($released) $this->assertEquals($released, $detail);
            $released = $detail;
        }
        $this->period->update(['is_submission_open' => true]);
        foreach ([$super, $office] as $actor) {
            $this->actingAs($actor);
            $this->getJson($this->url(['report' => 'individual', 'target_id' => $target]))->assertOk()->assertJsonPath('detail.rating', null)->assertJsonPath('detail.responses', null)->assertJsonPath('detail.sections', []);
        }
    }

    public function test_cross_office_roles_reassignment_guests_and_unverified_access_have_no_bypass(): void
    {
        $foreign = $this->other->staff()->create(['name' => 'Foreign confidential staff', 'position' => 'Assistant']);
        $target = hash('sha256', $foreign->id.':'.$this->period->id);
        $admin = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->office->id]);
        $this->actingAs($admin);
        $response = $this->getJson($this->url(['report' => 'summary']))->assertOk();
        $this->assertStringNotContainsString('Foreign confidential', $response->getContent());
        $this->getJson($this->url(['report' => 'summary', 'office_id' => $this->other->id]))->assertForbidden();
        $this->getJson($this->url(['report' => 'individual', 'target_id' => $target]))->assertNotFound();
        $admin->update(['office_id' => $this->other->id]);
        $this->getJson($this->url(['report' => 'summary', 'office_id' => $this->office->id]))->assertForbidden();
        $this->getJson($this->url(['report' => 'individual', 'target_id' => $target]))->assertOk();
        $admin->update(['office_id' => null]);
        $this->getJson($this->url(['report' => 'summary']))->assertForbidden();
        foreach (['department_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson($this->url(['report' => 'summary']))->assertForbidden();
            $this->get($this->url())->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'super_admin']));
        $this->get($this->url())->assertRedirect(route('verification.notice'));
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_applied_filters_full_export_pagination_and_zero_groups_reveal_no_small_counts(): void
    {
        for ($i = 0; $i < 12; $i++) $this->office->staff()->create(['name' => sprintf('Export Staff %02d', $i), 'position' => 'Synthetic Assistant']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $params = ['office_id' => $this->office->id, 'search' => 'Export Staff'];
        $props = $this->get($this->url($params))->assertOk()->viewData('page')['props'];
        $this->assertCount(10, $props['staff']['data']); $this->assertSame(12, $props['staff']['meta']['total']);
        $rows = $this->getJson($this->url([...$params, 'report' => 'summary', 'page' => 2]))->assertOk()->json('summaryRows');
        $this->assertCount(12, $rows);
        foreach ($rows as $row) { $this->assertNull($row['rating']); $this->assertNull($row['responses']); }
        $this->getJson($this->url(['report' => 'summary', 'office_id' => $this->other->id]))->assertOk()->assertJsonPath('summaryRows', []);
        $this->getJson($this->url(['report' => 'individual', 'target_id' => 'bad']))->assertUnprocessable();
        $this->getJson($this->url(['report' => 'summary', 'period_id' => 999999]))->assertUnprocessable();
    }

    public function test_empty_evaluation_migration_roundtrip_preserves_phase_three_records(): void
    {
        $migration = require database_path('migrations/2026_11_19_000002_create_staff_evaluation_tables.php');
        $before = DB::table('office_staff')->get()->toJson();
        $migration->down(); $migration->up();
        $this->assertSame($before, DB::table('office_staff')->get()->toJson());
        $this->assertDatabaseCount('staff_criteria_questions', 3);
        $this->submit()->assertSessionHasNoErrors();
    }
}
