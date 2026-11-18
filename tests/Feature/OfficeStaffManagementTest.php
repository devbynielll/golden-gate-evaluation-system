<?php

namespace Tests\Feature;

use App\Models\{Office, OfficeStaff, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficeStaffManagementTest extends TestCase
{
    use RefreshDatabase;
    private Office $office;
    private Office $other;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->office = Office::create(['code' => 'REG', 'name' => 'Synthetic Registrar']);
        $this->other = Office::create(['code' => 'LIB', 'name' => 'Synthetic Library']);
        $this->admin = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->office->id]);
    }

    private function payload(array $extra = []): array { return [...['name' => 'Synthetic Staff', 'position' => 'Records Assistant'], ...$extra]; }
    private function member(Office $office, string $name = 'Synthetic Staff'): OfficeStaff { return $office->staff()->create($this->payload(['name' => $name])); }

    public function test_both_authorized_roles_create_edit_and_archive_without_accounts_or_deletion(): void
    {
        foreach (['super_admin', 'office_admin'] as $role) {
            $actor = $role === 'office_admin' ? $this->admin : User::factory()->create(['role' => $role]);
            $this->actingAs($actor);
            $accounts = User::count();
            $this->post(route('offices.staff.store', $this->office), $this->payload(['name' => $role, 'is_active' => false]))->assertSessionHasNoErrors();
            $member = OfficeStaff::where('name', $role)->firstOrFail();
            $id = $member->id;
            $this->assertTrue($member->is_active);
            $this->put(route('offices.staff.update', [$this->office, $member]), $this->payload(['name' => $role.' corrected', 'position' => 'Senior Records Assistant']))->assertSessionHasNoErrors();
            $this->assertSame('Senior Records Assistant', $member->fresh()->position);
            $this->patch(route('offices.staff.archive', [$this->office, $member]))->assertSessionHasNoErrors();
            $this->assertFalse($member->fresh()->is_active);
            $this->assertSame($id, $member->fresh()->id);
            $this->assertSame($this->office->id, $member->fresh()->office_id);
            $this->assertSame($accounts, User::count());
            $this->patch(route('offices.staff.archive', [$this->office, $member]))->assertSessionHasNoErrors();
        }
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail());
        $this->delete(route('offices.destroy', $this->office))->assertSessionHasErrors('office');
        $this->assertDatabaseHas('offices', ['id' => $this->office->id]);
    }

    public function test_duplicates_include_archived_case_and_whitespace_variants_but_allow_other_positions_or_offices(): void
    {
        $this->actingAs($this->admin);
        $member = $this->member($this->office);
        $this->post(route('offices.staff.store', $this->office), $this->payload(['name' => ' SYNTHETIC   STAFF ', 'position' => 'records assistant']))->assertSessionHasErrors('name');
        $this->patch(route('offices.staff.archive', [$this->office, $member]));
        $this->post(route('offices.staff.store', $this->office), $this->payload())->assertSessionHasErrors('name');
        $this->post(route('offices.staff.store', $this->office), $this->payload(['position' => 'Office Head']))->assertSessionHasNoErrors();
        $this->member($this->other);
        $this->assertDatabaseCount('office_staff', 3);
        try { $this->member($this->office); $this->fail('Database uniqueness must backstop validation'); }
        catch (QueryException) { $this->assertDatabaseCount('office_staff', 3); }
        $this->post(route('offices.staff.store', $this->office), $this->payload(['name' => "\u{00A0}", 'position' => '']))->assertSessionHasErrors(['name', 'position']);
        $this->post(route('offices.staff.store', $this->office), $this->payload(['position' => str_repeat('x', 256)]))->assertSessionHasErrors('position');
        $this->assertDatabaseCount('office_staff', 3);
    }

    public function test_cross_office_nested_ids_context_and_reassigned_or_unassigned_actors_are_denied(): void
    {
        $own = $this->member($this->office);
        $foreign = $this->member($this->other, 'Other confidential staff');
        $this->actingAs($this->admin);
        $props = $this->get(route('staff.index'))->assertOk()->viewData('page')['props'];
        $this->assertCount(1, $props['staff']['data']);
        $this->assertStringNotContainsString('Other confidential', json_encode($props));
        $this->get(route('offices.staff.index', $this->other))->assertForbidden();
        $this->get(route('staff.index', ['office_id' => $this->other->id]))->assertForbidden();
        $this->post(route('offices.staff.store', $this->other), $this->payload())->assertForbidden();
        $this->put(route('offices.staff.update', [$this->office, $foreign]), $this->payload())->assertNotFound();
        $this->patch(route('offices.staff.archive', [$this->office, $foreign]))->assertNotFound();
        $this->put(route('offices.staff.update', [$this->office, $own]), $this->payload(['office_id' => $this->other->id]))->assertForbidden();
        $this->admin->update(['office_id' => $this->other->id]);
        $this->put(route('offices.staff.update', [$this->office, $own]), $this->payload())->assertForbidden();
        $this->assertSame($this->other->id, $this->get(route('staff.index'))->assertOk()->viewData('page')['props']['office']['id']);
        $this->admin->update(['office_id' => null]);
        $this->get(route('staff.index'))->assertForbidden();
    }

    public function test_department_student_guest_and_unverified_users_do_not_gain_staff_access(): void
    {
        $member = $this->member($this->office);
        foreach (['department_admin', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('offices.staff.index', $this->office))->assertForbidden();
            $this->post(route('offices.staff.store', $this->office), $this->payload())->assertForbidden();
            $this->put(route('offices.staff.update', [$this->office, $member]), $this->payload())->assertForbidden();
            $this->patch(route('offices.staff.archive', [$this->office, $member]))->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'super_admin']));
        $this->get(route('offices.staff.index', $this->office))->assertRedirect(route('verification.notice'));
        auth()->logout();
        $this->get(route('staff.index'))->assertRedirect(route('login'));
    }

    public function test_staff_filters_pagination_and_immutable_ownership(): void
    {
        $this->actingAs($this->admin);
        for ($i = 0; $i < 12; $i++) $this->member($this->office, sprintf('Staff %02d', $i));
        $member = OfficeStaff::firstOrFail();
        $this->patch(route('offices.staff.archive', [$this->office, $member]));
        $props = $this->get(route('staff.index', ['status' => 'all', 'page' => 2]))->assertOk()->viewData('page')['props'];
        $this->assertCount(2, $props['staff']['data']);
        $this->assertSame(12, $props['staff']['total']);
        $props = $this->get(route('staff.index', ['status' => 'archived', 'search' => 'Records Assistant']))->assertOk()->viewData('page')['props'];
        $this->assertCount(1, $props['staff']['data']);
        try { $member->delete(); $this->fail('Hard deletion must be blocked'); } catch (ValidationException $e) { $this->assertArrayHasKey('staff', $e->errors()); }
        $member->office_id = $this->other->id;
        try { $member->save(); $this->fail('Reassignment must be blocked'); } catch (ValidationException $e) { $this->assertArrayHasKey('office_id', $e->errors()); }
        $this->assertDatabaseHas('office_staff', ['id' => $member->id, 'office_id' => $this->office->id]);
    }
}
