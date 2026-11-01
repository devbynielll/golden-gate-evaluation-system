<?php

namespace Tests\Feature;

use App\Mail\EvaluationReminder;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// smoke test for admin pages that read offerings and enrollments
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_and_endpoints_work_with_regular_and_irregular_students(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $dept = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $program = $dept->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $term = ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester', 'is_active' => true, 'is_submission_open' => true,
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
        ]);
        $block = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = Subject::create(['program_id' => $program->id, 'code' => 'IT101', 'description' => 'Intro', 'year_level' => 1]);
        $faculty = $program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Ada']);
        ClassSubject::create(['class_section_id' => $block->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id, 'review_period_id' => $term->id]);

        foreach ([false, true] as $irregular) {
            $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'id_number' => $irregular ? 'I1' : 'R1']);
            $student = $program->students()->create([
                'user_id' => $user->id, 'student_number' => $user->id_number, 'email' => $user->email,
                'first_name' => 'S', 'last_name' => $user->id_number, 'is_irregular' => $irregular,
                'class_section_id' => $irregular ? null : $block->id,
            ]);
            if ($irregular) {
                $student->enrolledSubjects()->attach($subject->id, ['class_section_id' => $block->id]);
            }
        }

        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertOk();
        $props = $this->get(route('departments.programs.show', [$dept, $program]))->assertOk()->viewData('page')['props'];
        $this->assertSame($term->id, $props['term']['id']);
        $this->assertSame($block->id, collect($props['students'])->firstWhere('isIrregular', true)['enrolledCourses'][0]['classSectionId']);
        $this->get(route('departments.programs.show', [$dept, $program, 'term' => 999]))->assertOk(); // unknown term falls back
        $this->getJson(route('courses.search', ['program_id' => $program->id]))->assertOk()
            ->assertJsonPath('data.0.offerings.0.label', '1-A');
        $this->getJson(route('courses.filters'))->assertOk();
        $this->get(route('reports.faculty'))->assertOk();

        // reminders: single + bulk
        $this->post(route('students.remind', Student::first()), ['type' => 'faculty'])->assertRedirect();
        Mail::assertSent(EvaluationReminder::class, fn ($m) => $m->pending === ['IT101 - Prof. Ada']);
        $this->postJson(route('evaluations.remind-all'))->assertOk()->assertJsonStructure(['batchId']);
        Mail::assertQueued(EvaluationReminder::class, 2);
    }
}
