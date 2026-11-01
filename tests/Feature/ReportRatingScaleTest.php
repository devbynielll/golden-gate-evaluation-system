<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\CriteriaCategory;
use App\Models\Department;
use App\Models\Office;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// the Admin Reports overall rating must stay on the official 1.00-4.00 scale
class ReportRatingScaleTest extends TestCase
{
    use RefreshDatabase;

    private array $facultyQuestionIds = [];

    private array $officeQuestionIds = [];

    private ReviewPeriod $period;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->period = ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'is_active' => true, 'is_submission_open' => true,
        ]);

        $facultyCategory = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Teaching']);
        foreach ([1, 2] as $order) {
            $this->facultyQuestionIds[] = $facultyCategory->questions()->create(['text' => "FQ{$order}", 'order' => $order])->id;
        }

        $officeCategory = CriteriaCategory::create(['evaluatee_type' => 'office', 'section_number' => 1, 'name' => 'Service']);
        foreach ([1, 2] as $order) {
            $this->officeQuestionIds[] = $officeCategory->questions()->create(['text' => "OQ{$order}", 'order' => $order])->id;
        }
    }

    private function student(string $number, ClassSection $block): Student
    {
        $dept = Department::first() ?? Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $program = $block->program ?? Program::first();
        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'id_number' => $number, 'email' => "{$number}@x.test"]);

        return $program->students()->create([
            'user_id' => $user->id, 'student_number' => $number, 'email' => $user->email,
            'first_name' => 'S', 'last_name' => $number, 'is_irregular' => false,
            'class_section_id' => $block->id,
        ]);
    }

    public function test_faculty_overall_rating_stays_on_the_1_to_4_scale_and_uses_the_official_status_bands(): void
    {
        $dept = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $program = $dept->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $block = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = Subject::create(['program_id' => $program->id, 'code' => 'IT101', 'description' => 'Intro', 'year_level' => 1]);
        $faculty = $program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Ada']);
        $offering = ClassSubject::create([
            'class_section_id' => $block->id, 'subject_id' => $subject->id,
            'faculty_id' => $faculty->id, 'review_period_id' => $this->period->id,
        ]);

        // two students rate 4 and 3 -> average 3.50, which must land exactly on "Satisfactory"
        $ratingsFor = fn (int $value) => array_fill_keys($this->facultyQuestionIds, $value);
        $this->actingAs($this->student('R1', $block)->user)
            ->post(route('evaluation.faculty.store', $offering), ['ratings' => $ratingsFor(4)])
            ->assertRedirect();
        $this->actingAs($this->student('R2', $block)->user)
            ->post(route('evaluation.faculty.store', $offering), ['ratings' => $ratingsFor(3)])
            ->assertRedirect();

        Cache::flush();
        $props = $this->actingAs($this->admin)->get(route('reports.faculty'))->assertOk()->viewData('page')['props'];
        $row = collect($props['faculty']['data'])->firstWhere('id', $faculty->id);

        $this->assertSame(3.5, $row['rating']); // not rescaled to /6
        $this->assertSame('Satisfactory', $row['status']); // 2.51-3.50 band
    }

    public function test_office_overall_rating_uses_the_official_status_bands_across_the_full_range(): void
    {
        $cases = [
            // [rating given by every answer on the evaluation, expected status]
            [1, 'Very Unsatisfactory'], // 1.00-1.50
            [2, 'Unsatisfactory'],      // 1.51-2.50
            [3, 'Satisfactory'],        // 2.51-3.50
            [4, 'Very Satisfactory'],   // 3.51-4.00
        ];

        foreach ($cases as $i => [$value, $expectedStatus]) {
            $office = Office::create(['code' => "OFF{$i}", 'name' => "Office {$i}"]);
            $dept = Department::create(['code' => "D{$i}", 'name' => "Dept {$i}"]);
            $program = $dept->programs()->create(['code' => "P{$i}", 'name' => "Prog {$i}"]);
            $block = $program->classSections()->create(['year_level' => 1, 'block' => 'A']);
            $student = $this->student("O{$i}", $block);

            $this->actingAs($student->user)
                ->post(route('evaluation.office.store', $office), [
                    'ratings' => array_fill_keys($this->officeQuestionIds, $value),
                ])
                ->assertRedirect();

            Cache::flush();
            $props = $this->actingAs($this->admin)->get(route('reports.office'))->assertOk()->viewData('page')['props'];
            $row = collect($props['offices']['data'])->firstWhere('id', $office->id);

            $this->assertSame((float) $value, $row['rating']);
            $this->assertSame($expectedStatus, $row['status']);
        }
    }
}
