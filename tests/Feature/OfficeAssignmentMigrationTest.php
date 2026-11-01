<?php

namespace Tests\Feature;

use App\Models\{Office, User};
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class OfficeAssignmentMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // DDL commits on MySQL: use a fresh disposable schema, not a test transaction.
        // Exercise only this migration's rollback; never roll back unrelated history.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_additive_upgrade_preserves_legacy_accounts_and_credentials_without_mapping_any_office(): void
    {
        // Exercise ONLY the new migration's down/up on disposable test database, never the historical scope-removal migration.
        $migration = require database_path('migrations/2026_10_31_000001_add_office_assignment_to_users.php');
        $migration->down();$this->assertFalse(Schema::hasColumn('users','office_id'));
        DB::table('users')->insert(['name'=>'Legacy Office','email'=>'legacy-migration@example.invalid','password'=>'synthetic-preserved-hash','id_number'=>'LEGACY20','role'=>'office_admin','email_verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $before=DB::table('users')->where('email','legacy-migration@example.invalid')->first();
        Office::create(['code'=>'O20','name'=>'Unassigned synthetic office']);
        $migration->up();$after=DB::table('users')->where('email','legacy-migration@example.invalid')->first();
        foreach((array)$before as $key=>$value)$this->assertSame($value,$after->$key);
        $this->assertNull($after->office_id);$this->assertSame(1,User::where('role','office_admin')->count());
        $this->actingAs(User::find($after->id))->get(route('dashboard'))->assertForbidden();
        $this->get(route('reports.office'))->assertForbidden();
    }
    public function test_rollback_and_reapplication_keep_account_records_and_reinstate_actual_constraints(): void
    {
        $office = Office::create(['code' => 'ROLL22B', 'name' => 'Rollback test office']);
        $account = User::factory()->create(['role' => 'office_admin', 'office_id' => $office->id]);
        $before = $account->getAttributes(); unset($before['office_id']);
        $migration = require database_path('migrations/2026_10_31_000001_add_office_assignment_to_users.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'office_id'));
        foreach ($before as $key => $value) $this->assertSame($value, DB::table('users')->where('id', $account->id)->first()->$key);
        $migration->up();
        $this->assertNull($account->fresh()->office_id); // Removing the ownership column cannot retain its mapping.
        $this->assertTrue(collect(Schema::getIndexes('users'))->contains(fn ($index) => $index['name'] === 'users_office_assignment_unique' && $index['unique'] && $index['columns'] === ['office_id']));
        $account->refresh()->update(['office_id' => $office->id]);
        $duplicateRejected = false;
        try { User::factory()->create(['role' => 'office_admin', 'office_id' => $office->id]); }
        catch (\Illuminate\Database\UniqueConstraintViolationException) { $duplicateRejected = true; }
        $this->assertTrue($duplicateRejected);
        $this->assertSame(1, User::where('office_id', $office->id)->count());
        $office->delete();
        $this->assertNull($account->fresh()->office_id);
        $this->assertSame($before['password'], $account->fresh()->password);
    }
}
