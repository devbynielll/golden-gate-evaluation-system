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
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ClassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Program $program;
    private ClassSection $section;
    private ReviewPeriod $period;
    private array $subjects;
    private array $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]));
        $this->department = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $this->department->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $this->period = $this->term('1st Semester', true);
        foreach ([1, 2, 3] as $n) {
            $this->subjects[] = $this->program->subjects()->create(['code' => 'IT10'.$n, 'description' => 'Subject '.$n, 'year_level' => 1]);
        }
        foreach (['Ada', 'Bo'] as $name) {
            $this->faculty[] = $this->program->faculty()->create(['faculty_id' => $name, 'name' => $name]);
        }
    }

    private function term(string $semester, bool $active = false): ReviewPeriod
    {
        return ReviewPeriod::create([
            'academic_year' => '2026-2027', 'semester' => $semester,
            'start_date' => '2026-08-01', 'end_date' => '2026-12-01', 'is_active' => $active,
        ]);
    }

    private function assignments(?Faculty $first = null): array
    {
        return [
            ['subjectId' => $this->subjects[0]->id, 'instructorId' => ($first ?? $this->faculty[0])->id],
            ['subjectId' => $this->subjects[1]->id, 'instructorId' => $this->faculty[1]->id],
            ['subjectId' => $this->subjects[2]->id, 'instructorId' => null],
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['year' => 1, 'block' => 'A', 'termId' => $this->period->id, 'assignments' => $this->assignments()], $overrides);
    }

    private function url(string $action = 'update'): string
    {
        return route('programs.classes.'.$action, $action === 'store'
            ? [$this->department, $this->program]
            : [$this->department, $this->program, $this->section]);
    }

    private function offer(int $subject = 0, int $instructor = 0, ?ReviewPeriod $period = null): ClassSubject
    {
        return $this->section->classSubjects()->create([
            'subject_id' => $this->subjects[$subject]->id, 'faculty_id' => $this->faculty[$instructor]->id,
            'review_period_id' => ($period ?? $this->period)->id,
        ]);
    }

    private function version(): string
    {
        $props = $this->get(route('departments.programs.show', [$this->department, $this->program]))->assertOk()->viewData('page')['props'];

        return collect($props['classes'])->firstWhere('id', $this->section->id)['scheduleVersion'];
    }

    private function evaluate(ClassSubject $offering): Evaluation
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = $this->program->students()->create([
            'user_id' => $user->id, 'student_number' => $user->id_number, 'email' => $user->email,
            'first_name' => 'Synthetic', 'last_name' => 'Student', 'class_section_id' => $this->section->id,
        ]);
        $category = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Teaching']);
        $question = $category->questions()->create(['text' => 'Question', 'order' => 1]);
        $evaluation = Evaluation::create([
            'student_id' => $student->id, 'review_period_id' => $offering->review_period_id,
            'evaluatee_type' => 'faculty', 'class_subject_id' => $offering->id, 'comments' => 'Preserve', 'submitted_at' => now(),
        ]);
        $evaluation->answers()->create(['criteria_question_id' => $question->id, 'rating' => 4]);

        return $evaluation;
    }

    public function test_create_assigns_each_subject_independently_without_qualifications_and_keeps_tba(): void
    {
        $this->post($this->url('store'), $this->payload(['block' => 'B']))->assertRedirect()->assertSessionHasNoErrors();
        $section = $this->program->classSections()->where('block', 'B')->firstOrFail();
        foreach ($this->assignments() as $assignment) {
            $this->assertDatabaseHas('class_subjects', [
                'class_section_id' => $section->id, 'review_period_id' => $this->period->id,
                'subject_id' => $assignment['subjectId'], 'faculty_id' => $assignment['instructorId'],
            ]);
        }
        $this->assertDatabaseCount('faculty_subject', 0);
    }

    public function test_one_professor_can_teach_multiple_subjects_and_sections(): void
    {
        $assignments = array_map(fn ($subject) => ['subjectId' => $subject->id, 'instructorId' => $this->faculty[0]->id], $this->subjects);
        foreach (['B', 'C'] as $block) {
            $this->post($this->url('store'), $this->payload(['block' => $block, 'assignments' => $assignments]))
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(6, ClassSubject::where('faculty_id', $this->faculty[0]->id)->count());
        $props = $this->get(route('departments.programs.show', [$this->department, $this->program]))->assertOk()->viewData('page')['props'];
        $this->assertCount(2, $props['faculty']);
    }

    public function test_edit_round_trip_preserves_mixed_assignments_and_offering_ids(): void
    {
        $a = $this->offer();
        $b = $this->offer(1, 1);
        $props = $this->get(route('departments.programs.show', [$this->department, $this->program]))->assertOk()->viewData('page')['props'];
        $section = collect($props['classes'])->firstWhere('id', $this->section->id);
        $this->assertEqualsCanonicalizing(array_slice($this->assignments(), 0, 2), collect($section['assignments'])->all());
        $this->put($this->url(), $this->payload(['assignments' => collect($section['assignments'])->all(), 'scheduleVersion' => $section['scheduleVersion']]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$a->id, $b->id], $this->section->classSubjects()->orderBy('id')->pluck('id')->all());
        $this->assertSame($this->faculty[0]->id, $a->fresh()->faculty_id);
        $this->assertSame($this->faculty[1]->id, $b->fresh()->faculty_id);
    }

    public function test_unevaluated_assignment_updates_in_place_without_touching_other_terms(): void
    {
        $a = $this->offer();
        $other = $this->offer(0, 0, $this->term('2nd Semester'));
        $this->put($this->url(), $this->payload(['assignments' => [['subjectId' => $a->subject_id, 'instructorId' => null]]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->faculty_id);
        $this->assertSame($this->faculty[0]->id, $other->fresh()->faculty_id);
        $this->assertDatabaseCount('class_subjects', 2);
    }

    public function test_invalid_or_duplicate_assignments_cannot_partially_update_a_class(): void
    {
        $other = $this->department->programs()->create(['code' => 'OTHER', 'name' => 'Other']);
        $foreignSubject = $other->subjects()->create(['code' => 'X1', 'description' => 'Other', 'year_level' => 1]);
        $offering = $this->offer();
        $cases = [
            [[['subjectId' => $offering->subject_id, 'instructorId' => $this->faculty[0]->id], ['subjectId' => $offering->subject_id, 'instructorId' => null]], 'assignments.0.subjectId'],
            [[['subjectId' => $foreignSubject->id, 'instructorId' => null]], 'assignments.0.subjectId'],
            [[['subjectId' => $offering->subject_id, 'instructorId' => 999999]], 'assignments.0.instructorId'],
            [[['subjectId' => $offering->subject_id, 'instructorId' => 'not-an-id']], 'assignments.0.instructorId'],
            [[['subjectId' => $offering->subject_id]], 'assignments.0.instructorId'],
        ];
        foreach ($cases as [$assignments, $error]) {
            $this->put($this->url(), $this->payload(['block' => 'B', 'assignments' => $assignments]))->assertSessionHasErrors($error);
            $this->assertSame('A', $this->section->fresh()->block);
            $this->assertSame($this->faculty[0]->id, $offering->fresh()->faculty_id);
            $this->assertDatabaseCount('class_subjects', 1);
        }
    }

    public function test_evaluated_offerings_cannot_be_reassigned_removed_renamed_or_deleted(): void
    {
        $offering = $this->offer();
        $evaluation = $this->evaluate($offering);
        $before = $evaluation->fresh()->toArray();
        foreach ([$this->payload(['assignments' => [['subjectId' => $offering->subject_id, 'instructorId' => $this->faculty[1]->id]]]), $this->payload(['assignments' => []])] as $payload) {
            $this->put($this->url(), $payload)->assertSessionHasErrors('subjectIds');
        }
        $this->put($this->url(), $this->payload(['block' => 'B']))->assertSessionHasErrors('block');
        $this->delete(route('programs.classes.destroy', [$this->department, $this->program, $this->section]))->assertSessionHasErrors('subjectIds');
        $this->assertSame('A', $this->section->fresh()->block);
        $this->assertSame($this->faculty[0]->id, $offering->fresh()->faculty_id);
        $this->assertSame($before, $evaluation->fresh()->toArray());
        $this->assertDatabaseCount('evaluation_answers', 1);
    }

    public function test_evaluated_subject_can_stay_unchanged_while_other_subjects_are_added(): void
    {
        $offering = $this->offer();
        $this->evaluate($offering);
        $this->put($this->url(), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->faculty[0]->id, $offering->fresh()->faculty_id);
        $this->assertDatabaseCount('class_subjects', 3);
    }

    public function test_new_term_assignments_preserve_historical_instructor_and_evaluation(): void
    {
        $historical = $this->offer(0, 0, $this->term('2nd Semester'));
        $evaluation = $this->evaluate($historical);
        $this->put($this->url(), $this->payload(['assignments' => [['subjectId' => $historical->subject_id, 'instructorId' => $this->faculty[1]->id]]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->faculty[0]->id, $historical->fresh()->faculty_id);
        $this->assertSame($historical->id, $evaluation->fresh()->class_subject_id);
        $this->assertDatabaseHas('class_subjects', ['class_section_id' => $this->section->id, 'review_period_id' => $this->period->id, 'faculty_id' => $this->faculty[1]->id]);
    }

    public function test_stale_form_cannot_overwrite_a_newer_schedule_or_change_its_block(): void
    {
        $offering = $this->offer();
        $version = $this->version();
        $this->put($this->url(), $this->payload(['assignments' => [['subjectId' => $offering->subject_id, 'instructorId' => $this->faculty[1]->id]], 'scheduleVersion' => $version]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put($this->url(), $this->payload(['block' => 'B', 'scheduleVersion' => $version]))->assertSessionHasErrors('scheduleVersion');
        $this->assertSame('A', $this->section->fresh()->block);
        $this->assertSame($this->faculty[1]->id, $offering->fresh()->faculty_id);
    }

    public function test_a_schedule_version_cannot_be_reused_for_another_period(): void
    {
        $this->offer();
        $other = $this->term('2nd Semester');
        $this->put($this->url(), $this->payload(['termId' => $other->id, 'scheduleVersion' => $this->version()]))->assertSessionHasErrors('termId');
        $this->assertSame(0, ClassSubject::where('review_period_id', $other->id)->count());
    }

    public function test_legacy_uniform_assignment_requests_still_work_but_mixed_payloads_are_rejected(): void
    {
        $legacy = ['year' => 1, 'block' => 'A', 'termId' => $this->period->id, 'subjectIds' => [$this->subjects[0]->id, $this->subjects[1]->id], 'instructorId' => $this->faculty[0]->id];
        $this->put($this->url(), $legacy)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ClassSubject::where('faculty_id', $this->faculty[0]->id)->count());
        $this->put($this->url(), $this->payload(['subjectIds' => [$this->subjects[0]->id]]))->assertSessionHasErrors('subjectIds');
        $this->put($this->url(), array_replace($legacy, ['subjectIds' => [$this->subjects[0]->id, $this->subjects[0]->id]]))->assertSessionHasErrors('subjectIds.0');
    }

    public function test_transaction_rolls_back_section_and_offerings_when_a_later_write_fails(): void
    {
        Event::listen('eloquent.creating: '.ClassSubject::class, function (ClassSubject $offering) {
            if ($offering->subject_id === $this->subjects[1]->id) {
                throw new \RuntimeException('Simulated second offering failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($this->url('store'), $this->payload(['block' => 'B']));
            $this->fail('Expected the simulated write failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated second offering failure', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.ClassSubject::class);
        }
        $this->assertDatabaseCount('class_sections', 1);
        $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_update_transaction_restores_the_old_professor_if_a_later_offering_write_fails(): void
    {
        $offering = $this->offer();
        Event::listen('eloquent.creating: '.ClassSubject::class, function (ClassSubject $creating) {
            if ($creating->subject_id === $this->subjects[1]->id) {
                throw new \RuntimeException('Simulated update failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->put($this->url(), $this->payload(['year' => 2, 'block' => 'B', 'assignments' => $this->assignments($this->faculty[1])]));
            $this->fail('Expected the simulated write failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated update failure', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.ClassSubject::class);
        }
        $this->assertSame('A', $this->section->fresh()->block);
        $this->assertSame(1, $this->section->fresh()->year_level);
        $this->assertSame($this->faculty[0]->id, $offering->fresh()->faculty_id);
        $this->assertDatabaseCount('class_subjects', 1);
    }

    public function test_class_assignment_routes_keep_role_and_nested_program_boundaries(): void
    {
        $other = $this->department->programs()->create(['code' => 'OTHER', 'name' => 'Other']);
        $this->put(route('programs.classes.update', [$this->department, $other, $this->section]), $this->payload())->assertNotFound();
        foreach ([User::ROLE_STUDENT, User::ROLE_OFFICE_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->put($this->url(), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('class_subjects', 0);
    }
}
