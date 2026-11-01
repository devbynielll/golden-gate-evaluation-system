<?php

namespace Tests\Feature;

use App\Mail\EvaluationReminder;
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
use App\Services\AdminDashboardService;
use App\Services\StudentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// student -> enrollment -> evaluation board, submit and reminder email
class EvaluationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Program $program;

    private ReviewPeriod $period;

    private ClassSection $blockA;

    private ClassSection $blockB;

    /** @var array<string, ClassSubject> offerings keyed "BLOCK:CODE" */
    private array $offering = [];

    private array $questionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $dept = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $dept->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->period = ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'is_active' => true, 'is_submission_open' => true,
        ]);

        $category = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Teaching']);
        foreach ([1, 2] as $order) {
            $this->questionIds[] = $category->questions()->create(['text' => "Q{$order}", 'order' => $order])->id;
        }

        $this->blockA = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $this->blockB = $this->program->classSections()->create(['year_level' => 1, 'block' => 'B']);

        $fac = fn (string $id, string $name) => $this->program->faculty()->create(['faculty_id' => $id, 'name' => $name]);
        $subject = fn (string $code) => Subject::create(['program_id' => $this->program->id, 'code' => $code, 'description' => $code, 'year_level' => 1]);

        [$it101, $it102, $it103] = [$subject('IT101'), $subject('IT102'), $subject('IT103')];

        $this->offering['A:IT101'] = $this->offer($this->blockA, $it101, $fac('F1', 'Ada'));
        $this->offering['A:IT102'] = $this->offer($this->blockA, $it102, $fac('F2', 'Bo'));
        $this->offering['A:IT103'] = $this->offer($this->blockA, $it103, null); // no instructor yet
        $this->offering['B:IT101'] = $this->offer($this->blockB, $it101, $fac('F3', 'Cy'));
    }

    private function offer(ClassSection $section, Subject $subject, ?Faculty $faculty, ?int $periodId = null): ClassSubject
    {
        return ClassSubject::create([
            'class_section_id' => $section->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty?->id,
            'review_period_id' => $periodId ?? $this->period->id,
        ]);
    }

    private function student(string $number, ?ClassSection $section, bool $irregular = false, array $codes = []): Student
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'id_number' => $number, 'email' => "{$number}@x.test"]);
        $student = $this->program->students()->create([
            'user_id' => $user->id, 'student_number' => $number, 'email' => $user->email,
            'first_name' => 'S', 'last_name' => $number, 'is_irregular' => $irregular,
            'class_section_id' => $irregular ? null : $section?->id,
        ]);
        foreach ($codes as $key => $value) {
            [$code, $block] = is_int($key) ? [$value, null] : [$key, $value];
            $student->enrolledSubjects()->attach(Subject::where('code', $code)->value('id'), [
                'class_section_id' => $block ? ($block === 'A' ? $this->blockA : $this->blockB)->id : null,
            ]);
        }

        return $student;
    }

    private function board(Student $student): array
    {
        Cache::flush();

        return $this->actingAs($student->user)->get(route('evaluation.index'))->assertOk()->viewData('page')['props'];
    }

    private function subtitles(array $props): array
    {
        return collect($props['faculties']['data'])->pluck('subtitle')->map(fn ($s) => explode(' |', $s)[0])->sort()->values()->all();
    }

    private function ratings(): array
    {
        return array_fill_keys($this->questionIds, 4);
    }

    public function test_regular_student_sees_only_their_block_offerings_with_an_instructor(): void
    {
        $props = $this->board($this->student('R1', $this->blockA));

        $this->assertSame(['IT101', 'IT102'], $this->subtitles($props));
        $this->assertSame([$this->offering['A:IT101']->id, $this->offering['A:IT102']->id], collect($props['faculties']['data'])->pluck('id')->sort()->values()->all());
        $this->assertSame(2, $props['progress']['total'] - 0); // no offices in this fixture
        $this->assertStringContainsString('IT103', implode(' ', $props['warnings'])); // no instructor -> explained, not silently hidden
    }

    public function test_irregular_student_sees_only_enrolled_subjects(): void
    {
        $props = $this->board($this->student('I1', null, true, ['IT102']));

        $this->assertSame(['IT102'], $this->subtitles($props));
    }

    public function test_irregular_student_without_enrollments_gets_a_warning_not_a_guess(): void
    {
        $props = $this->board($this->student('I2', null, true, []));

        $this->assertSame([], $this->subtitles($props));
        $this->assertStringContainsString('No enrolled subjects', $props['warnings'][0]);
    }

    public function test_regular_student_without_a_block_gets_a_warning(): void
    {
        $props = $this->board($this->student('R2', null));

        $this->assertSame([], $this->subtitles($props));
        $this->assertStringContainsString('No year/block', $props['warnings'][0]);
    }

    public function test_cannot_submit_for_a_subject_the_student_is_not_enrolled_in(): void
    {
        $regular = $this->student('R3', $this->blockA);
        $irregular = $this->student('I3', null, true, ['IT102']);

        // other block's offering / unenrolled subject
        $this->actingAs($regular->user)
            ->post(route('evaluation.faculty.store', $this->offering['B:IT101']), ['ratings' => $this->ratings()])->assertForbidden();
        $this->actingAs($irregular->user)
            ->post(route('evaluation.faculty.store', $this->offering['A:IT101']), ['ratings' => $this->ratings()])->assertForbidden();
        // enrolled but no instructor -> nothing to evaluate
        $this->actingAs($regular->user)
            ->post(route('evaluation.faculty.store', $this->offering['A:IT103']), ['ratings' => $this->ratings()])->assertNotFound();

        $this->assertSame(0, Evaluation::count());
    }

    public function test_resubmitting_never_creates_a_duplicate_or_overwrites_history(): void
    {
        $student = $this->student('R4', $this->blockA);
        $url = route('evaluation.faculty.store', $this->offering['A:IT101']);

        $this->actingAs($student->user)->post($url, ['ratings' => $this->ratings(), 'comments' => 'first'])->assertRedirect();
        $this->actingAs($student->user)->post($url, ['ratings' => array_fill_keys($this->questionIds, 1), 'comments' => 'second'])->assertRedirect();

        $this->assertSame(1, Evaluation::count());
        $this->assertSame('first', Evaluation::first()->comments);
        $this->assertSame(2, Evaluation::first()->answers()->count());
    }

    public function test_changing_enrollment_keeps_history_and_does_not_inflate_progress(): void
    {
        $student = $this->student('I4', null, true, ['IT101' => 'A', 'IT102' => 'A']);
        $this->actingAs($student->user)
            ->post(route('evaluation.faculty.store', $this->offering['A:IT101']), ['ratings' => $this->ratings()]);

        // IT101 is later dropped from the student's enrollment
        $student->enrolledSubjects()->detach(Subject::where('code', 'IT101')->value('id'));

        $this->assertSame(1, Evaluation::count());                   // history kept
        $props = $this->board($student->fresh());
        $this->assertSame(['IT102'], $this->subtitles($props));
        $this->assertSame(0, $props['progress']['completed']);        // not counted against the current load
        $this->assertSame(1, $props['progress']['total']);
    }

    public function test_irregular_student_only_gets_the_block_they_attend(): void
    {
        $student = $this->student('I5', null, true, ['IT101' => 'B']);

        $props = $this->board($student);
        $this->assertSame([$this->offering['B:IT101']->id], collect($props['faculties']['data'])->pluck('id')->all());
        $this->assertStringContainsString('Cy', $props['faculties']['data'][0]['cardTitle']);

        // the other block's instructor for the same subject is not evaluable
        $this->actingAs($student->user)
            ->post(route('evaluation.faculty.store', $this->offering['A:IT101']), ['ratings' => $this->ratings()])->assertForbidden();
        $this->actingAs($student->user)
            ->post(route('evaluation.faculty.store', $this->offering['B:IT101']), ['ratings' => $this->ratings()])->assertRedirect();
        $this->assertSame($this->offering['B:IT101']->id, Evaluation::first()->class_subject_id);
    }

    public function test_enrollment_without_a_block_is_never_guessed_when_the_subject_has_several(): void
    {
        // IT101 is in A and B so it's hidden, IT102 has one offering
        $props = $this->board($this->student('I6', null, true, ['IT101', 'IT102']));

        $this->assertSame(['IT102'], $this->subtitles($props));
        $this->assertStringContainsString('IT101', implode(' ', $props['warnings']));
        $this->assertStringContainsString('block is not recorded', implode(' ', $props['warnings']));
    }

    public function test_dashboard_expectations_follow_the_attended_block(): void
    {
        $student = $this->student('I7', null, true, ['IT101' => 'B', 'IT102' => 'A']);
        $dashboard = app(AdminDashboardService::class);

        $targets = fn () => collect($dashboard->pendingReminderTargets($this->period, null, null))->where('type', 'faculty')->count();
        $this->assertSame(1, $targets());   // expects 2 (one per attended block), not 3 (every IT101 + IT102)

        foreach (['B:IT101', 'A:IT102'] as $key) {
            $this->actingAs($student->user)
                ->post(route('evaluation.faculty.store', $this->offering[$key]), ['ratings' => $this->ratings()]);
        }
        Cache::flush();
        $this->assertSame(0, $targets());   // fully evaluated -> no longer pending
    }

    public function test_reminder_email_lists_only_pending_subjects_and_links_to_the_evaluation_page(): void
    {
        $student = $this->student('R5', $this->blockA);
        $this->actingAs($student->user)
            ->post(route('evaluation.faculty.store', $this->offering['A:IT101']), ['ratings' => $this->ratings()]);

        $pending = app(StudentEvaluationService::class)->pendingFacultyLabels($student, $this->period);
        $this->assertSame(['IT102 - Prof. Bo'], $pending);

        $mail = new EvaluationReminder($student, 'their faculty members', $pending);
        $html = $mail->render();

        $this->assertStringContainsString(route('evaluation.index'), $html);
        $this->assertStringContainsString('IT102 - Prof. Bo', $html);
        $this->assertStringNotContainsString('IT101 - Prof. Ada', $html);
    }

    public function test_evaluation_link_requires_login_and_returns_to_the_evaluation_page(): void
    {
        $this->get(route('evaluation.index'))->assertRedirect(route('login'));
        $this->assertSame(route('evaluation.index'), session('url.intended'));
    }
}
