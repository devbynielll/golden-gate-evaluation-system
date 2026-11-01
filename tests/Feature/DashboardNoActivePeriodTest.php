<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\CriteriaCategory;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Office;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardNoActivePeriodTest extends TestCase
{
    use RefreshDatabase;

    private array $admins;

    private Student $irregular;

    private $program;

    private $section;

    private $subject;

    private $faculty;

    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $department = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $department->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $this->subject = $this->program->subjects()->create(['code' => 'IT101', 'description' => 'Intro', 'year_level' => 1]);
        $this->faculty = $this->program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Ada']);
        $this->office = Office::create(['code' => 'REC', 'name' => 'Records']);

        foreach ([false, true] as $irregular) {
            $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
            $student = $this->program->students()->create([
                'user_id' => $user->id, 'student_number' => $irregular ? 'I1' : 'R1', 'email' => $user->email,
                'first_name' => 'Synthetic', 'last_name' => $irregular ? 'Irregular' : 'Regular',
                'is_irregular' => $irregular, 'class_section_id' => $irregular ? null : $this->section->id,
            ]);
            if ($irregular) {
                $this->irregular = $student;
            }
        }

        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_DEPARTMENT_ADMIN, User::ROLE_OFFICE_ADMIN] as $role) {
            $this->admins[$role] = User::factory()->create(['role' => $role, 'office_id' => $role === User::ROLE_OFFICE_ADMIN ? $this->office->id : null]);
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'students', 'class_subjects', 'student_subject_enrollments', 'review_periods', 'evaluations', 'evaluation_answers'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    private function assertAdminOverviews(): void
    {
        foreach ($this->admins as $role => $admin) {
            $props = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->viewData('page')['props'];

            if ($role === User::ROLE_OFFICE_ADMIN) {
                $this->assertSame(1, $props['summary']['offices']);
                $this->assertNull($props['summary']['term']);
                $this->assertSame(0, $props['summary']['responses']);
                $this->assertSame(0, $props['summary']['progress']);
                $this->assertSame('No Data', $props['summary']['status']);

                continue;
            }

            $this->assertFalse($props['isReviewOpen']);
            $this->assertSame(2, $props['totalStudents']);
            $this->assertSame(1, $props['totalFaculty']);
            $this->assertSame(0, $props['facultyPending']);
            $this->assertSame(0, $props['facultyProgress']);
            $this->assertSame(2, $props['evaluations']['meta']['total']);

            if ($role === User::ROLE_SUPER_ADMIN) {
                $this->assertTrue($props['showOffices']);
                $this->assertSame(1, $props['totalOffices']);
                $this->assertSame(0, $props['officePending']);
                $this->assertSame(0, $props['officeProgress']);
            } else {
                $this->assertFalse($props['showOffices']);
                foreach (['totalOffices', 'officePending', 'officeProgress'] as $key) {
                    $this->assertArrayNotHasKey($key, $props);
                }
                foreach ($props['evaluations']['data'] as $student) {
                    $this->assertSame(['faculty'], collect($student['submissions'])->pluck('type')->all());
                }
            }
        }
    }

    public function test_all_admin_dashboards_work_without_creating_a_review_period(): void
    {
        $before = $this->snapshot();

        $this->assertAdminOverviews();

        $this->assertDatabaseCount('review_periods', 0);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_no_active_period_dashboards_preserve_enrollment_and_historical_reports(): void
    {
        $period = ReviewPeriod::create([
            'academic_year' => '2025-2026', 'semester' => '1st Semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-01',
            'is_active' => false, 'is_submission_open' => false,
        ]);
        $offering = ClassSubject::create([
            'class_section_id' => $this->section->id, 'subject_id' => $this->subject->id,
            'faculty_id' => $this->faculty->id, 'review_period_id' => $period->id,
        ]);
        $this->irregular->enrolledSubjects($period->id)->attach($this->subject->id, ['class_section_id' => $this->section->id]);

        foreach (['faculty', 'office'] as $type) {
            $category = CriteriaCategory::create(['evaluatee_type' => $type, 'section_number' => 1, 'name' => 'Historical '.$type]);
            $question = $category->questions()->create(['text' => 'Historical question', 'order' => 1]);
            $evaluation = Evaluation::create([
                'student_id' => $this->irregular->id, 'review_period_id' => $period->id,
                'evaluatee_type' => $type, 'submitted_at' => '2025-11-01 12:00:00',
                'class_subject_id' => $type === 'faculty' ? $offering->id : null,
                'office_id' => $type === 'office' ? $this->office->id : null,
            ]);
            $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => 3]);
        }
        $before = $this->snapshot();

        $this->assertAdminOverviews();

        $this->actingAs($this->admins[User::ROLE_SUPER_ADMIN]);
        $faculty = $this->get(route('reports.faculty', ['period_id' => $period->id]))->assertOk()->viewData('page')['props']['faculty']['data'][0];
        $office = $this->get(route('reports.office', ['period_id' => $period->id]))->assertOk()->viewData('page')['props']['offices']['data'][0];
        foreach ([$faculty, $office] as $row) {
            $this->assertSame(1, $row['responses']);
            $this->assertEquals(3, $row['rating']);
        }
        $this->assertSame([$this->subject->id], $this->irregular->enrolledSubjects($period->id)->pluck('subjects.id')->all());
        $this->assertNull(ReviewPeriod::activeId());
        $this->assertSame($before, $this->snapshot());
    }
}
