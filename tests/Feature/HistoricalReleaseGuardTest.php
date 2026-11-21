<?php

namespace Tests\Feature;

use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, Office, ReviewPeriod, Subject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class HistoricalReleaseGuardTest extends TestCase
{
    use RefreshDatabase;
    private $department;
    private $program;
    private $student;
    private $faculty;
    private $period;
    private $offering;
    private $office;
    private $category;
    private $question;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); Storage::fake('public');
        $this->department = Department::create(['code'=>'SYN','name'=>'Synthetic Department']);
        $this->program = $this->department->programs()->create(['code'=>'SYN','name'=>'Synthetic Program']);
        $user = User::factory()->create(['role'=>'student']);
        $this->student = $this->program->students()->create(['user_id'=>$user->id,'student_number'=>'SYN001','email'=>$user->email,'first_name'=>'Synthetic','last_name'=>'Student']);
        $this->faculty = $this->program->faculty()->create(['faculty_id'=>'SYN-F','name'=>'Synthetic Faculty','photo_path'=>'faculty-photos/synthetic.jpg']);
        Storage::disk('public')->put($this->faculty->photo_path, 'synthetic photo bytes');
        $section = $this->program->classSections()->create(['year_level'=>1,'block'=>'A']);
        $subject = Subject::create(['program_id'=>$this->program->id,'code'=>'SYN101','description'=>'Synthetic Subject','year_level'=>1]);
        $this->period = ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>now()->subMonth(),'end_date'=>now()->subDay(),'is_active'=>true,'is_submission_open'=>false]);
        $this->offering = ClassSubject::create(['class_section_id'=>$section->id,'subject_id'=>$subject->id,'faculty_id'=>$this->faculty->id,'review_period_id'=>$this->period->id]);
        $this->office = Office::create(['code'=>'SYN','name'=>'Synthetic Office']);
        $this->category = CriteriaCategory::create(['evaluatee_type'=>'faculty','section_number'=>1,'name'=>'Original Faculty Section']);
        $this->question = $this->category->questions()->create(['text'=>'Original historical wording','order'=>1]);
        $this->actingAs(User::factory()->create(['role'=>'super_admin']));
    }

    private function record(string $type = 'faculty'): void
    {
        $evaluation = Evaluation::create(['student_id'=>$this->student->id,'review_period_id'=>$this->period->id,'evaluatee_type'=>$type,
            'class_subject_id'=>$type==='faculty'?$this->offering->id:null,'office_id'=>$type==='office'?$this->office->id:null,'submitted_at'=>now()]);
        $evaluation->answers()->create(['criteria_question_id'=>$this->question->id,'rating'=>4]);
    }

    private function snapshot(): array
    {
        $result=[]; foreach (['students','users','faculty','class_subjects','evaluations','evaluation_answers','criteria_categories','criteria_questions'] as $table) $result[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        return $result;
    }

    public function test_both_authorized_roles_cannot_delete_students_with_legacy_history_or_their_login(): void
    {
        $this->record('office');
        $departmentAdmin = User::factory()->create(['role'=>'department_admin']);
        foreach ([User::where('role','super_admin')->firstOrFail(), $departmentAdmin] as $actor) {
            $this->actingAs($actor); $before=$this->snapshot();
            $this->delete(route('programs.students.destroy',[$this->department,$this->program,$this->student]))->assertSessionHasErrors('student');
            $this->assertSame($before,$this->snapshot());
        }
        // Faculty-only history follows the same guard; no dependence on Office or Staff responses.
        DB::table('evaluation_answers')->delete(); DB::table('evaluations')->delete(); $this->record();
        $before=$this->snapshot();
        $this->delete(route('programs.students.destroy',[$this->department,$this->program,$this->student]))->assertSessionHasErrors('student');
        $this->assertSame($before,$this->snapshot());
    }

    public function test_evaluated_faculty_deletion_preserves_attribution_answers_and_photo_for_both_roles(): void
    {
        $this->record();
        $departmentAdmin = User::factory()->create(['role'=>'department_admin']);
        foreach ([User::where('role','super_admin')->firstOrFail(),$departmentAdmin] as $actor) {
            $this->actingAs($actor); $before=$this->snapshot();
            $this->delete(route('programs.faculty.destroy',[$this->department,$this->program,$this->faculty]))->assertSessionHasErrors('faculty');
            $this->assertSame($before,$this->snapshot()); Storage::disk('public')->assertExists($this->faculty->photo_path);
        }
    }

    public function test_super_cannot_rewrite_or_remove_answered_faculty_wording_or_sections_even_after_closure(): void
    {
        $this->record(); $before=$this->snapshot();
        $category='/admin/criteria/faculty/categories/'.$this->category->id;
        $question=$category.'/questions/'.$this->question->id;
        foreach ([[$question,['text'=>'Rewrite']],[$category,['name'=>'Rewrite','section'=>2]]] as [$url,$data]) {
            $this->put($url,$data)->assertSessionHasErrors('criteria'); $this->delete($url)->assertSessionHasErrors('criteria');
        }
        $this->assertSame($before,$this->snapshot());
        $props=$this->get(route('criteria.faculty'))->assertOk()->viewData('page')['props'];
        $this->assertContains($this->question->id,$props['criteriaPermissions']['usedQuestionIds']);
        $this->post($category.'/questions',['text'=>'New unused question'])->assertSessionHasNoErrors();
        $new=$this->category->questions()->latest('id')->firstOrFail();
        $this->put($category.'/questions/'.$new->id,['text'=>'Corrected unused question'])->assertSessionHasNoErrors();
        $this->assertSame('Original historical wording',$this->question->fresh()->text);
        $this->period->update(['is_submission_open'=>true]);
        $this->post($category.'/questions',['text'=>'Blocked while open'])->assertSessionHasErrors('criteria');
    }

    public function test_unevaluated_student_and_faculty_deletion_remains_supported(): void
    {
        $id=$this->student->user_id;
        $this->delete(route('programs.students.destroy',[$this->department,$this->program,$this->student]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('students',['id'=>$this->student->id]); $this->assertDatabaseMissing('users',['id'=>$id]);
        $this->delete(route('programs.faculty.destroy',[$this->department,$this->program,$this->faculty]))->assertSessionHasNoErrors();
        $this->assertNull($this->offering->fresh()->faculty_id); Storage::disk('public')->assertMissing($this->faculty->photo_path);
    }

    public function test_parent_deletions_cannot_bypass_history_guards(): void
    {
        $this->record(); $before=$this->snapshot();
        $this->delete(route('departments.programs.destroy',[$this->department,$this->program]))->assertRedirect();
        $this->delete(route('departments.destroy',$this->department))->assertRedirect();
        $this->assertSame($before,$this->snapshot());
    }
}
