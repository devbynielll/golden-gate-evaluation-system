<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, Faculty, ReviewPeriod, Student, User};
use App\Services\ReportService;
use App\Services\StudentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CrossProgramFacultyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Department $homeDepartment;
    private $program;
    private $otherProgram;
    private ReviewPeriod $period;
    private array $subjects;
    private Faculty $homeFaculty;
    private Faculty $visitingFaculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]));
        $this->homeDepartment = Department::create(['code' => 'HOME', 'name' => 'Home Department']);
        $this->program = $this->homeDepartment->programs()->create(['code' => 'HOME-P', 'name' => 'Home Program']);
        $otherDepartment = Department::create(['code' => 'VISIT', 'name' => 'Visiting Department']);
        $this->otherProgram = $otherDepartment->programs()->create(['code' => 'VISIT-P', 'name' => 'Visiting Program']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_submission_open' => true]);
        foreach (['HOME101', 'HOME102', 'HOME103'] as $code) $this->subjects[] = $this->program->subjects()->create(['code' => $code, 'description' => $code, 'year_level' => 1]);
        $this->homeFaculty = $this->program->faculty()->create(['faculty_id' => 'HOME-F', 'name' => 'Alex Same']);
        $this->visitingFaculty = $this->otherProgram->faculty()->create(['faculty_id' => 'VISIT-F', 'name' => 'Alex Same']);
    }

    private function assignments(): array
    {
        return [
            ['subjectId' => $this->subjects[0]->id, 'instructorId' => $this->visitingFaculty->id],
            ['subjectId' => $this->subjects[1]->id, 'instructorId' => $this->homeFaculty->id],
            ['subjectId' => $this->subjects[2]->id, 'instructorId' => null],
        ];
    }

    private function classUrl(string $action, $section = null): string
    {
        return route('programs.classes.'.$action, $section
            ? [$this->homeDepartment, $this->program, $section]
            : [$this->homeDepartment, $this->program]);
    }

    public function test_workspace_lists_labeled_institution_faculty_for_class_assignment_without_widening_the_program_faculty_tab(): void
    {
        $props = $this->get(route('departments.programs.show', [$this->homeDepartment, $this->program]))->assertOk()->viewData('page')['props'];
        $this->assertSame([$this->homeFaculty->id], collect($props['faculty'])->pluck('id')->all());
        $visiting = collect($props['classFaculty'])->firstWhere('id', $this->visitingFaculty->id);
        $this->assertSame('VISIT-F', $visiting['facultyId']);
        $this->assertSame('Alex Same — VISIT-F · VISIT / VISIT-P', $visiting['label']);
    }

    public function test_manual_class_assignment_allows_cross_program_same_program_and_tba_without_moving_faculty(): void
    {
        $this->post($this->classUrl('store'), ['year' => 1, 'block' => 'A', 'termId' => $this->period->id, 'assignments' => $this->assignments()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $section = $this->program->classSections()->sole();
        foreach ($this->assignments() as $assignment) {
            $this->assertDatabaseHas('class_subjects', [
                'class_section_id' => $section->id,
                'review_period_id' => $this->period->id,
                'subject_id' => $assignment['subjectId'],
                'faculty_id' => $assignment['instructorId'],
            ]);
        }
        $this->assertSame($this->otherProgram->id, $this->visitingFaculty->fresh()->program_id);
        $this->assertSame(1, ClassSubject::where('faculty_id', $this->visitingFaculty->id)->count());
    }

    public function test_forged_or_missing_faculty_ids_are_rejected_without_changing_existing_assignments(): void
    {
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $offering = $section->classSubjects()->create(['subject_id' => $this->subjects[0]->id, 'faculty_id' => $this->visitingFaculty->id, 'review_period_id' => $this->period->id]);
        $this->put($this->classUrl('update', $section), ['year' => 1, 'block' => 'A', 'termId' => $this->period->id, 'assignments' => [['subjectId' => $this->subjects[0]->id, 'instructorId' => 999999]]])
            ->assertSessionHasErrors('assignments.0.instructorId');
        $this->assertSame($this->visitingFaculty->id, $offering->fresh()->faculty_id);
    }

    public function test_class_import_accepts_cross_program_faculty_but_rejects_missing_ids_without_reassignment(): void
    {
        $url = route('programs.classes.import.preview', [$this->homeDepartment, $this->program]);
        $csv = "year,block,subject,faculty\n1,A,HOME101,VISIT-F\n1,B,HOME102,MISSING\n";
        $token = $this->postJson($url, ['termId' => $this->period->id, 'file' => UploadedFile::fake()->createWithContent('classes.csv', $csv)])->assertOk()->json('token');
        $id = $this->postJson(route('programs.classes.import.store', [$this->homeDepartment, $this->program]), ['termId' => $this->period->id, 'token' => $token, 'startRow' => 2, 'mapping' => ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3]])->assertOk()->json('batchId');
        $batch = \App\Models\ImportBatch::findOrFail($id);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame(1, $batch->failure_count);
        $this->assertDatabaseHas('class_subjects', ['subject_id' => $this->subjects[0]->id, 'faculty_id' => $this->visitingFaculty->id]);
        $this->assertDatabaseMissing('class_sections', ['block' => 'B']);
    }

    public function test_cross_program_offering_remains_evaluable_reportable_and_locked_after_submission(): void
    {
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $offering = $section->classSubjects()->create(['subject_id' => $this->subjects[0]->id, 'faculty_id' => $this->visitingFaculty->id, 'review_period_id' => $this->period->id]);
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = $this->program->students()->create(['user_id' => $studentUser->id, 'student_number' => $studentUser->id_number, 'email' => $studentUser->email, 'first_name' => 'Demo', 'last_name' => 'Student', 'class_section_id' => $section->id]);
        $question = CriteriaCategory::create(['evaluatee_type' => 'faculty', 'section_number' => 1, 'name' => 'Teaching'])->questions()->create(['text' => 'Clear?', 'order' => 1]);
        $board = app(StudentEvaluationService::class)->board($student, $this->period, 1, 1);
        $this->assertSame([$offering->id], collect($board['faculties']['data'])->pluck('id')->all());
        $this->actingAs($studentUser)->post(route('evaluation.faculty.store', $offering), ['ratings' => [$question->id => 4]])->assertRedirect();
        $this->actingAs(User::where('role', User::ROLE_DEPARTMENT_ADMIN)->sole());
        $this->put($this->classUrl('update', $section), ['year' => 1, 'block' => 'A', 'termId' => $this->period->id, 'assignments' => [['subjectId' => $this->subjects[0]->id, 'instructorId' => $this->homeFaculty->id]]])
            ->assertSessionHasErrors('subjectIds');
        $this->assertSame($this->visitingFaculty->id, $offering->fresh()->faculty_id);
        $this->assertSame(1, Evaluation::where('class_subject_id', $offering->id)->count());
        $report = app(ReportService::class)->facultyReport(null, $this->period->id, 1);
        $this->assertSame(1, collect($report['data'])->firstWhere('id', $this->visitingFaculty->id)['responses']);
    }
}
