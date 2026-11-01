<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\CriteriaCategory;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// offerings and enrollments are per term
class TermScopingTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    private Program $program;

    private ReviewPeriod $fall;

    private ReviewPeriod $spring;

    private ClassSection $block;

    private Subject $it101;

    private Faculty $ada;

    private Faculty $bo;

    private User $admin;

    private int $questionId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->dept = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $this->dept->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->fall = $this->period('2026-2027', '1st Semester', active: true);
        $this->spring = $this->period('2026-2027', '2nd Semester');

        $category = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'T']);
        $this->questionId = $category->questions()->create(['text' => 'Q1', 'order' => 1])->id;

        $this->block = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $this->it101 = Subject::create(['program_id' => $this->program->id, 'code' => 'IT101', 'description' => 'x', 'year_level' => 1]);
        $this->ada = $this->program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Ada']);
        $this->bo = $this->program->faculty()->create(['faculty_id' => 'F2', 'name' => 'Bo']);
    }

    private function period(string $year, string $semester, bool $active = false): ReviewPeriod
    {
        return ReviewPeriod::create([
            'academic_year' => $year, 'semester' => $semester,
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'is_active' => $active, 'is_submission_open' => $active,
        ]);
    }

    private function offer(ReviewPeriod $term, Faculty $faculty): ClassSubject
    {
        return ClassSubject::create([
            'class_section_id' => $this->block->id, 'subject_id' => $this->it101->id,
            'faculty_id' => $faculty->id, 'review_period_id' => $term->id,
        ]);
    }

    private function student(string $number, bool $irregular = false): Student
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'id_number' => $number, 'email' => "{$number}@x.test"]);

        return $this->program->students()->create([
            'user_id' => $user->id, 'student_number' => $number, 'email' => $user->email,
            'first_name' => 'S', 'last_name' => $number, 'is_irregular' => $irregular,
            'class_section_id' => $irregular ? null : $this->block->id,
        ]);
    }

    private function board(Student $student): array
    {
        Cache::flush();

        return $this->actingAs($student->user)->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
    }

    private function cardIds(array $props): array
    {
        return collect($props['faculties']['data'])->pluck('id')->all();
    }

    public function test_regular_student_only_sees_the_active_terms_offerings(): void
    {
        $fallOffering = $this->offer($this->fall, $this->ada);
        $springOffering = $this->offer($this->spring, $this->bo);
        $student = $this->student('R1');

        $this->assertSame([$fallOffering->id], $this->cardIds($this->board($student)));

        // other term's offering is blocked even by id
        $this->actingAs($student->user)
            ->post(route('evaluation.faculty.store', $springOffering), ['ratings' => [$this->questionId => 5]])->assertForbidden();

        // activating spring switches the board, fall history stays
        $this->actingAs($student->user)->post(route('evaluation.faculty.store', $fallOffering), ['ratings' => [$this->questionId => 4]]);
        $this->actingAs($this->admin)->patch(route('review-periods.activate', $this->spring));
        $props = $this->board($student->fresh());
        $this->assertSame([$springOffering->id], $this->cardIds($props));
        $this->assertSame(0, $props['progress']['completed']);
        $this->assertSame(1, Evaluation::where('review_period_id', $this->fall->id)->count());
    }

    public function test_irregular_enrollment_does_not_carry_over_to_the_next_term(): void
    {
        $this->offer($this->fall, $this->ada);
        $springOffering = $this->offer($this->spring, $this->bo);
        $student = $this->student('I1', irregular: true);
        $student->enrolledSubjects()->attach($this->it101->id, ['class_section_id' => $this->block->id]); // fall

        $this->assertCount(1, $this->cardIds($this->board($student)));

        $this->actingAs($this->admin)->patch(route('review-periods.activate', $this->spring));
        $props = $this->board($student->fresh());
        $this->assertSame([], $this->cardIds($props));
        $this->assertStringContainsString('No enrolled subjects are recorded for you in 2026-2027 2nd Semester', implode(' ', $props['warnings']));

        // enrolling for spring doesn't touch the fall record
        $student->fresh()->enrolledSubjects()->attach($this->it101->id, ['class_section_id' => $this->block->id]);
        $this->assertSame([$springOffering->id], $this->cardIds($this->board($student->fresh())));
        $this->assertSame(1, $student->enrolledSubjects($this->fall->id)->count());
        $this->assertSame(1, $student->enrolledSubjects($this->spring->id)->count());
    }

    public function test_warns_when_the_active_term_has_no_schedule_at_all(): void
    {
        $this->offer($this->spring, $this->bo); // only the inactive term is scheduled

        $props = $this->board($this->student('R2'));

        $this->assertSame([], $this->cardIds($props));
        $this->assertStringContainsString('No classes have been scheduled for 2026-2027 1st Semester', $props['warnings'][0]);
    }

    public function test_editing_a_class_keeps_offerings_that_have_evaluations(): void
    {
        $offering = $this->offer($this->fall, $this->ada);
        $student = $this->student('R3');
        $this->actingAs($student->user)->post(route('evaluation.faculty.store', $offering), ['ratings' => [$this->questionId => 4]]);

        $url = route('programs.classes.update', [$this->dept, $this->program, $this->block]);
        $payload = fn (array $over = []) => array_merge([
            'year' => 1, 'block' => 'A', 'termId' => $this->fall->id,
            'subjectIds' => [$this->it101->id], 'instructorId' => $this->ada->id,
        ], $over);

        // same schedule keeps the evaluated row
        $this->actingAs($this->admin)->put($url, $payload())->assertSessionHasNoErrors();
        $this->assertSame($offering->id, ClassSubject::where('review_period_id', $this->fall->id)->value('id'));

        // changing instructor or dropping the subject is refused
        $this->actingAs($this->admin)->put($url, $payload(['instructorId' => $this->bo->id]))->assertSessionHasErrors('subjectIds');
        $this->actingAs($this->admin)->put($url, $payload(['subjectIds' => []]))->assertSessionHasErrors('subjectIds');
        $this->assertSame($this->ada->id, $offering->fresh()->faculty_id);

        // Inactive-period edits are now explicitly rejected; evaluated history remains untouched.
        $this->actingAs($this->admin)->put($url, $payload(['termId' => $this->spring->id, 'instructorId' => $this->bo->id]))
            ->assertSessionHasErrors('termId');
        $this->assertSame(0, ClassSubject::where('review_period_id', $this->spring->id)->count());
        $this->assertSame($this->ada->id, $offering->fresh()->faculty_id);
    }

    public function test_copy_schedule_only_fills_gaps_and_never_copies_enrollments(): void
    {
        $this->offer($this->fall, $this->ada);
        $this->offer($this->spring, $this->bo); // already scheduled in the target: must stay Bo
        $student = $this->student('I2', irregular: true);
        $student->enrolledSubjects()->attach($this->it101->id, ['class_section_id' => $this->block->id]);

        $second = Subject::create(['program_id' => $this->program->id, 'code' => 'IT102', 'description' => 'y', 'year_level' => 1]);
        ClassSubject::create(['class_section_id' => $this->block->id, 'subject_id' => $second->id, 'faculty_id' => $this->ada->id, 'review_period_id' => $this->fall->id]);

        $this->actingAs($this->admin)
            ->post(route('programs.schedule.copy', [$this->dept, $this->program]), ['from' => $this->fall->id, 'to' => $this->spring->id])
            ->assertSessionHasNoErrors();

        $spring = ClassSubject::where('review_period_id', $this->spring->id)->get();
        $this->assertCount(2, $spring);
        $this->assertSame($this->bo->id, $spring->firstWhere('subject_id', $this->it101->id)->faculty_id);
        $this->assertSame(0, $student->enrolledSubjects($this->spring->id)->count());
    }

    public function test_first_review_period_adopts_unassigned_data(): void
    {
        DB::table('review_periods')->delete();
        $offering = ClassSubject::create(['class_section_id' => $this->block->id, 'subject_id' => $this->it101->id, 'faculty_id' => $this->ada->id]);

        $this->actingAs($this->admin)->post(route('review-periods.store'), [
            'academic_year' => '2027-2028', 'semester' => '1st Semester',
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame(ReviewPeriod::value('id'), $offering->fresh()->review_period_id);
    }

    public function test_enrolling_subjects_requires_an_active_term(): void
    {
        $this->offer($this->fall, $this->ada);
        $this->fall->update(['is_active' => false]);

        $this->actingAs($this->admin)->post(route('programs.students.store', [$this->dept, $this->program]), [
            'studentId' => 'X1', 'email' => 'x1@x.test', 'firstName' => 'X', 'lastName' => 'One',
            'isIrregular' => true, 'enrollments' => [['subjectId' => $this->it101->id, 'classSectionId' => $this->block->id]],
        ])->assertSessionHasErrors('subjectIds');

        $this->assertSame(0, Student::count());
    }

    public function test_class_edits_show_up_for_students_immediately(): void
    {
        $this->offer($this->fall, $this->ada);
        $student = $this->student('R9');
        $this->assertStringContainsString('Ada', $this->board($student)['faculties']['data'][0]['cardTitle']);

        // prime the cache, then edit through the endpoint
        $this->actingAs($student->user)->get(route('evaluation.index'));
        $this->actingAs($this->admin)->put(route('programs.classes.update', [$this->dept, $this->program, $this->block]), [
            'year' => 1, 'block' => 'A', 'termId' => $this->fall->id,
            'subjectIds' => [$this->it101->id], 'instructorId' => $this->bo->id,
        ])->assertSessionHasNoErrors();

        $props = $this->actingAs($student->user)->get(route('evaluation.index'))->viewData('page')['props'];
        $this->assertStringContainsString('Bo', $props['faculties']['data'][0]['cardTitle']);
    }

    public function test_course_search_by_instructor_only_matches_the_active_term(): void
    {
        $this->offer($this->fall, $this->ada);
        $this->offer($this->spring, $this->bo);

        $search = fn (string $name) => $this->actingAs($this->admin)
            ->getJson(route('courses.search', ['q' => $name]))->assertOk()->json('data');

        $this->assertCount(1, $search('Ada'));
        $this->assertCount(0, $search('Bo')); // only teaches in the inactive term
    }
}
