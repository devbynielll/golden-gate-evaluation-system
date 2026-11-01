<?php

namespace Tests\Feature;

use App\Mail\AdminAccountCreated;
use App\Models\ClassSubject;
use App\Models\CriteriaCategory;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\ImportBatch;
use App\Models\Office;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// one super admin, one department admin (all departments, no offices), one office admin (all offices)
class RoleScopingTest extends TestCase
{
    use RefreshDatabase;

    private Department $csDept;

    private Department $eduDept;

    private Program $csProgram;

    private Program $eduProgram;

    private Office $csOffice;

    private Office $eduOffice;

    private Office $globalOffice;

    private ReviewPeriod $period;

    private User $super;

    private User $deptAdmin;

    private User $officeAdmin;

    private Student $csStudent;

    private Student $eduStudent;

    private $csFaculty;

    private $eduFaculty;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->csDept = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->eduDept = Department::create(['code' => 'EDU', 'name' => 'Education']);
        $this->csProgram = $this->csDept->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->eduProgram = $this->eduDept->programs()->create(['code' => 'BEED', 'name' => 'Elem Ed']);

        $this->csOffice = Office::create(['department_id' => $this->csDept->id, 'code' => 'CSO', 'name' => 'CS Office']);
        $this->eduOffice = Office::create(['department_id' => $this->eduDept->id, 'code' => 'EDO', 'name' => 'Edu Office']);
        $this->globalOffice = Office::create(['department_id' => null, 'code' => 'REC', 'name' => 'Records']);

        $this->period = ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'is_active' => true, 'is_submission_open' => true,
        ]);

        $this->super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->deptAdmin = User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]);
        $this->officeAdmin = User::factory()->create(['role' => User::ROLE_OFFICE_ADMIN, 'office_id' => $this->csOffice->id]);

        [$this->csStudent, $this->csFaculty] = $this->enrolled($this->csProgram, 'CS1', 'F-CS');
        [$this->eduStudent, $this->eduFaculty] = $this->enrolled($this->eduProgram, 'ED1', 'F-ED');
    }

    // one regular student with one instructor
    private function enrolled(Program $program, string $number, string $facultyId): array
    {
        $block = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = Subject::create(['program_id' => $program->id, 'code' => "{$number}-SUB", 'description' => 'Subject', 'year_level' => 1]);
        $faculty = $program->faculty()->create(['faculty_id' => $facultyId, 'name' => "Prof {$facultyId}"]);
        ClassSubject::create([
            'class_section_id' => $block->id, 'subject_id' => $subject->id,
            'faculty_id' => $faculty->id, 'review_period_id' => $this->period->id,
        ]);

        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'id_number' => $number, 'email' => "{$number}@x.test"]);
        $student = $program->students()->create([
            'user_id' => $user->id, 'student_number' => $number, 'email' => $user->email,
            'first_name' => 'S', 'last_name' => $number, 'is_irregular' => false,
            'class_section_id' => $block->id,
        ]);

        return [$student, $faculty];
    }

    private function props($response): array
    {
        return $response->assertOk()->viewData('page')['props'];
    }

    // ---- who can reach what

    public function test_department_admin_is_blocked_from_super_admin_only_pages(): void
    {
        $this->actingAs($this->deptAdmin);

        $this->get(route('criteria.faculty'))->assertOk();

        foreach (['offices.index', 'accounts.index', 'criteria.office', 'reports.office'] as $name) {
            $this->get(route($name))->assertForbidden();
        }

        $this->post(route('departments.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->post(route('review-periods.store'), [])->assertForbidden();
        $this->post(route('accounts.store'), [])->assertForbidden();
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_office_admin_is_blocked_from_everything_but_their_dashboard_and_office_report(): void
    {
        $this->actingAs($this->officeAdmin);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('reports.office'))->assertOk();

        foreach (['departments.index', 'offices.index', 'accounts.index', 'reports.faculty', 'criteria.faculty'] as $name) {
            $this->get(route($name))->assertForbidden();
        }

        $this->get(route('departments.show', $this->csDept))->assertForbidden();
        $this->post(route('evaluations.remind-all'))->assertForbidden();
        $this->get(route('courses.search'))->assertForbidden();
    }

    public function test_students_cannot_reach_any_admin_page(): void
    {
        $this->actingAs($this->csStudent->user);

        foreach (['departments.index', 'reports.faculty', 'reports.office', 'accounts.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_department_admin_manages_every_department(): void
    {
        $this->actingAs($this->deptAdmin);

        $this->get(route('departments.index'))->assertOk();
        $this->get(route('departments.show', $this->csDept))->assertOk();
        $this->get(route('departments.show', $this->eduDept))->assertOk();
        $this->get(route('departments.programs.show', [$this->eduDept, $this->eduProgram]))->assertOk();

        // but a program still has to belong to the department in the URL
        $this->get(route('departments.programs.show', [$this->csDept, $this->eduProgram]))->assertNotFound();

        // adding departments stays with the super admin
        $this->post(route('departments.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    // ---- what each admin sees

    public function test_department_admin_dashboard_has_every_department_but_no_offices(): void
    {
        $super = $this->props($this->actingAs($this->super)->get(route('dashboard')));
        $this->assertTrue($super['showOffices']);
        $this->assertSame(3, $super['totalOffices']);
        $this->assertEqualsCanonicalizing(
            ['faculty', 'office'],
            collect($super['evaluations']['data'][0]['submissions'])->pluck('type')->all(),
        );

        $dept = $this->props($this->actingAs($this->deptAdmin)->get(route('dashboard')));
        $this->assertFalse($dept['showOffices']);
        $this->assertSame(2, $dept['totalStudents']);
        $this->assertSame(2, $dept['totalFaculty']);
        $this->assertEqualsCanonicalizing(['CS', 'EDU'], collect($dept['departmentBreakdown'])->pluck('code')->all());
        $this->assertFalse($dept['canManagePeriods']);

        foreach (['totalOffices', 'officePending', 'officeProgress'] as $key) {
            $this->assertArrayNotHasKey($key, $dept);
            $this->assertArrayHasKey($key, $super);
        }
        $this->assertSame(2, $dept['facultyPending']);
        $this->assertSame(0, $dept['facultyProgress']);
        $this->assertTrue($dept['isReviewOpen']);
        foreach ($dept['departmentBreakdown'] as $department) {
            $this->assertArrayNotHasKey('officeProgress', $department);
            $this->assertArrayHasKey('facultyProgress', $department);
            $this->assertArrayHasKey('students', $department);
        }

        // no office rows in the feed either
        foreach ($dept['evaluations']['data'] as $student) {
            $this->assertSame(['faculty'], collect($student['submissions'])->pluck('type')->all());
        }

        // Project a response copy, never remove office values from the shared cache.
        $superAgain = $this->props($this->actingAs($this->super)->get(route('dashboard')));
        foreach (['totalOffices', 'officePending', 'officeProgress', 'departmentBreakdown', 'evaluations', 'filterOptions'] as $key) {
            $this->assertEquals($super[$key], $superAgain[$key]);
        }
    }

    public function test_department_dashboard_projection_also_works_without_an_active_period(): void
    {
        $this->period->update(['is_active' => false]);
        Cache::flush();

        $dept = $this->props($this->actingAs($this->deptAdmin)->get(route('dashboard')));
        $this->assertFalse($dept['showOffices']);
        $this->assertFalse($dept['isReviewOpen']);
        $this->assertSame(0, $dept['facultyPending']);
        $this->assertSame(0, $dept['facultyProgress']);
        $this->assertSame(2, $dept['totalStudents']);
        $this->assertSame(2, $dept['totalFaculty']);
        foreach (['totalOffices', 'officePending', 'officeProgress'] as $key) {
            $this->assertArrayNotHasKey($key, $dept);
        }
        foreach ($dept['departmentBreakdown'] as $department) {
            $this->assertArrayNotHasKey('officeProgress', $department);
            $this->assertSame(0, $department['facultyProgress']);
        }

        $super = $this->props($this->actingAs($this->super)->get(route('dashboard')));
        $this->assertSame(3, $super['totalOffices']);
        $this->assertArrayHasKey('officeProgress', $super['departmentBreakdown'][0]);

        $office = $this->props($this->actingAs($this->officeAdmin)->get(route('dashboard')));
        $this->assertSame(1, $office['summary']['offices']);
        $this->assertSame(0, $office['summary']['responses']);
    }

    public function test_department_dashboard_keeps_faculty_filters_while_omitting_office_metrics(): void
    {
        $filters = ['department_id' => $this->csDept->id, 'program_id' => $this->csProgram->id];
        $dept = $this->props($this->actingAs($this->deptAdmin)->get(route('dashboard', $filters)));

        $this->assertEquals($filters, $dept['selectedFilters']);
        $this->assertSame(1, $dept['evaluations']['meta']['total']);
        $this->assertSame($this->csStudent->id, $dept['evaluations']['data'][0]['id']);
        $this->assertSame(['faculty'], collect($dept['evaluations']['data'][0]['submissions'])->pluck('type')->all());
        $this->assertEqualsCanonicalizing(['CS', 'EDU'], collect($dept['departmentBreakdown'])->pluck('code')->all());
        $this->assertNotEmpty($dept['filterOptions']);
        $this->assertArrayNotHasKey('officePending', $dept);
    }

    public function test_faculty_report_covers_every_department_for_the_department_admin(): void
    {
        $dept = $this->props($this->actingAs($this->deptAdmin)->get(route('reports.faculty')));
        $this->assertEqualsCanonicalizing(['F-CS', 'F-ED'], collect($dept['faculty']['data'])->pluck('facultyId')->all());
    }

    public function test_office_report_covers_only_assigned_office_for_the_office_admin(): void
    {
        $own = $this->props($this->actingAs($this->officeAdmin)->get(route('reports.office')));
        $this->assertCount(1, $own['offices']['data']);
        $this->assertSame($this->csOffice->id, $own['offices']['data'][0]['id']);
    }

    public function test_office_dashboard_summarises_only_the_assigned_office(): void
    {
        $category = CriteriaCategory::create(['evaluatee_type' => 'office', 'section_number' => 1, 'name' => 'Service']);
        $question = $category->questions()->create(['text' => 'Q', 'order' => 1]);

        // cs student rates cs office 4, edu student rates edu office 1
        foreach ([[$this->csStudent, $this->csOffice, 4], [$this->eduStudent, $this->eduOffice, 1]] as [$student, $office, $rating]) {
            $evaluation = Evaluation::create([
                'review_period_id' => $this->period->id, 'student_id' => $student->id,
                'evaluatee_type' => 'office', 'office_id' => $office->id, 'submitted_at' => now(),
            ]);
            $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => $rating]);
        }

        $summary = $this->props($this->actingAs($this->officeAdmin)->get(route('dashboard')))['summary'];

        $this->assertSame(1, $summary['offices']);
        $this->assertSame(4.0, (float) $summary['rating']);
        $this->assertSame(1, $summary['responses']);
        $this->assertSame(1, $summary['expected']);
        $this->assertSame(0, $summary['pending']);
        $this->assertSame(100, $summary['progress']);
    }

    // ---- reminders and import batches

    public function test_department_admin_reminds_for_faculty_only(): void
    {
        Mail::fake();
        $this->actingAs($this->deptAdmin);

        $this->post(route('students.remind', $this->eduStudent), ['type' => 'faculty'])->assertRedirect();
        $this->post(route('students.remind', $this->csStudent), ['type' => 'faculty'])->assertRedirect();

        // office reminders aren't a department admin's job
        $this->post(route('students.remind', $this->csStudent), ['type' => 'office'])->assertForbidden();

        // bulk reminder: both students, faculty only
        $batchId = $this->postJson(route('evaluations.remind-all'))->assertOk()->json('batchId');
        $this->assertSame(2, ImportBatch::find($batchId)->total_rows);

        $mine = ImportBatch::find($batchId);
        $theirs = ImportBatch::create(['type' => 'reminder', 'total_rows' => 1, 'created_by' => $this->super->id]);

        $this->getJson(route('import-batches.show', $mine))->assertOk();
        $this->getJson(route('import-batches.show', $theirs))->assertForbidden();

        $this->actingAs($this->super)->getJson(route('import-batches.show', $mine))->assertOk();
    }

    // ---- account management

    public function test_super_admin_creates_a_department_admin_and_the_login_is_emailed(): void
    {
        Mail::fake();
        $this->deptAdmin->delete(); // the one slot has to be free

        $this->actingAs($this->super)->post(route('accounts.store'), [
            'role' => User::ROLE_DEPARTMENT_ADMIN, 'name' => 'Dean Cruz', 'email' => 'dean@x.test',
        ])->assertRedirect();

        $user = User::where('email', 'dean@x.test')->firstOrFail();
        $this->assertSame(User::ROLE_DEPARTMENT_ADMIN, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertStringStartsWith('DPT-', $user->id_number);
        Mail::assertSent(AdminAccountCreated::class, fn ($mail) => $mail->hasTo('dean@x.test') && strlen($mail->password) >= 12);
    }

    public function test_single_department_admin_and_single_admin_per_office_rules_are_preserved(): void
    {
        Mail::fake();
        $this->actingAs($this->super);

        // setUp already made one of each
        $this->post(route('accounts.store'), [
            'role' => User::ROLE_DEPARTMENT_ADMIN, 'name' => 'Second Dean', 'email' => 'dean2@x.test',
        ])->assertSessionHasErrors('role');

        $this->post(route('accounts.store'), [
            'role' => User::ROLE_OFFICE_ADMIN, 'name' => 'Second Registrar', 'email' => 'reg2@x.test', 'office_id' => $this->csOffice->id,
        ])->assertSessionHasErrors('office_id');

        $this->assertDatabaseMissing('users', ['email' => 'dean2@x.test']);
        $this->assertDatabaseMissing('users', ['email' => 'reg2@x.test']);
        Mail::assertNothingSent();

        // the accounts page tells the form which roles are taken
        $taken = $this->props($this->get(route('accounts.index')))['takenRoles'];
        $this->assertEqualsCanonicalizing([User::ROLE_DEPARTMENT_ADMIN], $taken);
    }

    public function test_super_admin_creates_the_office_admin_once_the_slot_is_free(): void
    {
        Mail::fake();
        $this->officeAdmin->delete();

        $this->actingAs($this->super)->post(route('accounts.store'), [
            'role' => User::ROLE_OFFICE_ADMIN, 'name' => 'Registrar', 'email' => 'reg@x.test', 'office_id' => $this->csOffice->id,
        ])->assertRedirect();

        $user = User::where('email', 'reg@x.test')->firstOrFail();
        $this->assertSame(User::ROLE_OFFICE_ADMIN, $user->role);
        $this->assertStringStartsWith('OFC-', $user->id_number);
    }

    public function test_account_creation_validates_role_and_email(): void
    {
        Mail::fake();
        $this->deptAdmin->delete();
        $this->actingAs($this->super);

        // can't create super admins or students here
        $this->post(route('accounts.store'), ['role' => User::ROLE_SUPER_ADMIN, 'name' => 'A', 'email' => 'a@x.test'])
            ->assertSessionHasErrors('role');
        $this->post(route('accounts.store'), ['role' => User::ROLE_DEPARTMENT_ADMIN, 'name' => 'A', 'email' => $this->super->email])
            ->assertSessionHasErrors('email');
        $this->post(route('accounts.store'), ['role' => User::ROLE_DEPARTMENT_ADMIN, 'email' => 'a@x.test'])
            ->assertSessionHasErrors('name');

        Mail::assertNothingSent();
    }

    public function test_account_is_not_created_when_the_login_email_cannot_be_sent(): void
    {
        $this->deptAdmin->delete();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($this->super)->post(route('accounts.store'), [
            'role' => User::ROLE_DEPARTMENT_ADMIN, 'name' => 'Dean', 'email' => 'dean@x.test',
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'dean@x.test']);
    }

    public function test_super_admin_edits_resets_and_deletes_an_account_but_cannot_change_its_role(): void
    {
        Mail::fake();
        $this->actingAs($this->super);

        $this->put(route('accounts.update', $this->deptAdmin), [
            'name' => 'New Name', 'email' => $this->deptAdmin->email,
        ])->assertRedirect();
        $this->assertSame('New Name', $this->deptAdmin->fresh()->name);

        $this->put(route('accounts.update', $this->deptAdmin), [
            'role' => User::ROLE_SUPER_ADMIN, 'name' => 'X', 'email' => 'x@x.test',
        ])->assertSessionHasErrors('role');
        $this->assertSame(User::ROLE_DEPARTMENT_ADMIN, $this->deptAdmin->fresh()->role);

        $oldHash = $this->deptAdmin->password;
        $this->post(route('accounts.reset-password', $this->deptAdmin))->assertRedirect();
        $this->assertNotSame($oldHash, $this->deptAdmin->fresh()->password);
        Mail::assertSent(AdminAccountCreated::class, fn ($mail) => $mail->reset);

        $this->delete(route('accounts.destroy', $this->deptAdmin))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $this->deptAdmin->id]);
    }

    public function test_accounts_page_can_never_touch_super_admins_or_students(): void
    {
        $this->actingAs($this->super);

        foreach ([$this->super, $this->csStudent->user] as $target) {
            $this->delete(route('accounts.destroy', $target))->assertNotFound();
            $this->post(route('accounts.reset-password', $target))->assertNotFound();
            $this->put(route('accounts.update', $target), ['name' => 'X', 'email' => 'x@x.test'])->assertNotFound();
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }

        $listed = $this->props($this->get(route('accounts.index')))['accounts']['data'];
        $listed = collect($listed)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->deptAdmin->id, $this->officeAdmin->id], $listed);
    }

    public function test_deleting_a_department_or_office_leaves_the_admins_alone(): void
    {
        $this->actingAs($this->super);

        Department::create(['code' => 'TMP', 'name' => 'Temp'])->delete();
        $this->csOffice->delete();

        $this->assertDatabaseHas('users', ['id' => $this->deptAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $this->officeAdmin->id]);
    }
}
