<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;
class DepartmentAdminSingletonTest extends TestCase {
 use RefreshDatabase;
 public function test_database_rejects_duplicate_creation_but_allows_authorized_replacement(): void {
  $first=User::factory()->create(['role'=>'department_admin']);
  try {User::factory()->create(['role'=>'department_admin']);$this->fail('Duplicate permitted');}catch(UniqueConstraintViolationException){$this->assertSame(1,User::where('role','department_admin')->count());}
  $this->assertNotNull($first->fresh());$first->delete();$replacement=User::factory()->create(['role'=>'department_admin']);$this->assertSame('department_admin',$replacement->fresh()->role);
 }
 public function test_direct_role_update_is_also_constrained(): void {
  User::factory()->create(['role'=>'department_admin']);$other=User::factory()->create(['role'=>'student']);
  $this->expectException(UniqueConstraintViolationException::class);$other->update(['role'=>'department_admin']);
 }
}
