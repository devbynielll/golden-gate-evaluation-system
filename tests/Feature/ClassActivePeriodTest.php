<?php

namespace Tests\Feature;

use App\Jobs\ImportClassRowsJob;
use App\Models\{ClassSubject, Department, ImportBatch, ReviewPeriod, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Tests\TestCase;

class ClassActivePeriodTest extends TestCase
{
    use RefreshDatabase;

    private $department;
    private $program;
    private $active;
    private $past;
    private $section;
    private $subject;
    private $faculty;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp(); Cache::flush(); Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'department_admin']);
        $this->actingAs($this->admin);
        $this->department = Department::create(['code' => 'C18', 'name' => 'Synthetic']);
        $this->program = $this->department->programs()->create(['code' => 'P18', 'name' => 'Synthetic']);
        $this->active = $this->period('1st Semester', true);
        $this->past = $this->period('2nd Semester');
        $this->section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $this->subject = $this->program->subjects()->create(['code' => 'S18', 'description' => 'Synthetic', 'year_level' => 1]);
        $this->faculty = $this->program->faculty()->create(['faculty_id' => 'F18', 'name' => 'Synthetic']);
    }

    private function period(string $semester, bool $active = false)
    {
        return ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => $semester, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => $active]);
    }

    private function url(string $action = 'store'): string
    {
        return route('programs.classes.'.$action, [$this->department, $this->program, ...($action === 'store' ? [] : [$this->section])]);
    }

    private function importUrl(string $action): string
    {
        return route('programs.classes.import.'.$action, [$this->department, $this->program]);
    }

    private function payload(array $over = []): array
    {
        return array_replace(['year' => 1, 'block' => 'A', 'termId' => $this->active->id, 'assignments' => [['subjectId' => $this->subject->id, 'instructorId' => $this->faculty->id]]], $over);
    }

    private function upload(): string
    {
        return $this->postJson($this->importUrl('preview'), ['termId' => $this->active->id, 'file' => UploadedFile::fake()->createWithContent('classes.csv', "year,block,subject,faculty\n1,B,S18,F18\n")])->assertOk()->json('token');
    }

    private function importPayload(string $token, ?int $termId = null): array
    {
        return ['termId' => $termId ?? $this->active->id, 'token' => $token, 'startRow' => 2, 'mapping' => ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3]];
    }

    public function test_normal_workspace_uses_active_period_and_explicit_history_is_read_only(): void
    {
        $history = $this->section->classSubjects()->create(['review_period_id' => $this->past->id, 'subject_id' => $this->subject->id, 'faculty_id' => $this->faculty->id]);
        $url = route('departments.programs.show', [$this->department, $this->program]);
        $props = $this->get($url)->assertOk()->viewData('page')['props'];
        $this->assertSame($this->active->id, $props['term']['id']); $this->assertTrue($props['canScheduleClasses']);
        $props = $this->get($url.'?term='.$this->past->id)->assertOk()->viewData('page')['props'];
        $this->assertSame($this->past->id, $props['term']['id']); $this->assertFalse($props['canScheduleClasses']);
        $this->assertSame($this->faculty->id, $props['classes'][0]['assignments'][0]['instructorId']);
        $this->assertDatabaseHas('class_subjects', ['id' => $history->id]);
    }

    public function test_no_active_period_never_falls_back_to_latest_historical_period(): void
    {
        $this->active->update(['is_active' => false]);
        $props = $this->get(route('departments.programs.show', [$this->department, $this->program]))->assertOk()->viewData('page')['props'];
        $this->assertNull($props['term']); $this->assertFalse($props['canScheduleClasses']);
        $this->post($this->url(), $this->payload(['block' => 'B']))->assertSessionHasErrors('termId');
        $this->put($this->url('update'), $this->payload())->assertSessionHasErrors('termId');
        $this->delete($this->url('destroy'), ['termId' => $this->active->id])->assertSessionHasErrors('termId');
        $this->postJson($this->importUrl('preview'), ['termId' => $this->active->id])->assertUnprocessable()->assertJsonValidationErrors('termId');
        $this->assertDatabaseCount('class_sections', 1); $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_no_active_workspace_with_existing_students_is_readable_without_changing_enrollments(): void
    {
        foreach ([false, true] as $irregular) {
            $user = User::factory()->create(['role' => 'student']);
            $student = $this->program->students()->create(['user_id' => $user->id, 'student_number' => 'S'.(int) $irregular, 'email' => $user->email, 'first_name' => 'Synthetic', 'last_name' => 'Student', 'is_irregular' => $irregular, 'class_section_id' => $irregular ? null : $this->section->id]);
            if ($irregular) $student->enrolledSubjects($this->past->id)->attach($this->subject->id, ['class_section_id' => $this->section->id]);
        }
        $before = DB::table('student_subject_enrollments')->get()->toJson();
        $this->active->update(['is_active' => false]);
        $props = $this->get(route('departments.programs.show', [$this->department, $this->program]))->assertOk()->viewData('page')['props'];
        $this->assertNull($props['term']); $this->assertFalse($props['canScheduleClasses']); $this->assertCount(2, $props['students']);
        $this->assertSame($before, DB::table('student_subject_enrollments')->get()->toJson());
    }

    public function test_both_admin_roles_create_and_edit_only_active_assignments(): void
    {
        foreach (['super_admin', 'department_admin'] as $index => $role) {
            $this->actingAs($role === 'department_admin' ? $this->admin : User::factory()->create(['role' => $role]));
            $this->post($this->url(), $this->payload(['block' => 'B'.$index]))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($this->admin)->put($this->url('update'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_subjects', 3);
        $this->assertSame([$this->active->id], ClassSubject::distinct()->pluck('review_period_id')->all());
        $this->assertSame([$this->faculty->id], ClassSubject::distinct()->pluck('faculty_id')->all());
    }

    public function test_inactive_period_ids_cannot_create_update_delete_or_import(): void
    {
        $before = $this->section->fresh()->toArray();
        $this->post($this->url(), $this->payload(['block' => 'B', 'termId' => $this->past->id]))->assertSessionHasErrors('termId');
        $this->put($this->url('update'), $this->payload(['termId' => $this->past->id]))->assertSessionHasErrors('termId');
        $this->delete($this->url('destroy'), ['termId' => $this->past->id])->assertSessionHasErrors('termId');
        $this->postJson($this->importUrl('preview'), ['termId' => $this->past->id])->assertUnprocessable()->assertJsonValidationErrors('termId');
        $this->postJson($this->importUrl('store'), $this->importPayload('ignored', $this->past->id))->assertUnprocessable()->assertJsonValidationErrors('termId');
        $this->assertSame($before, $this->section->fresh()->toArray()); $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_stale_forms_and_tokens_cannot_follow_a_changed_active_period(): void
    {
        $token = $this->upload();
        $this->active->update(['is_active' => false]); $this->past->update(['is_active' => true]);
        $this->put($this->url('update'), $this->payload())->assertSessionHasErrors('termId');
        $this->post($this->url(), $this->payload(['block' => 'C']))->assertSessionHasErrors('termId');
        $this->postJson($this->importUrl('store'), $this->importPayload($token))->assertUnprocessable()->assertJsonValidationErrors('termId');
        $this->postJson($this->importUrl('store'), $this->importPayload($token, $this->past->id))->assertForbidden();
        $this->assertDatabaseCount('class_subjects', 0); $this->assertDatabaseCount('class_sections', 1);
    }

    public function test_active_import_adds_only_current_period_offerings_and_preserves_history(): void
    {
        $old = $this->section->classSubjects()->create(['review_period_id' => $this->past->id, 'subject_id' => $this->subject->id, 'faculty_id' => $this->faculty->id]);
        $before = $old->fresh()->toArray();
        $id = $this->postJson($this->importUrl('store'), $this->importPayload($this->upload()))->assertOk()->json('batchId');
        $this->assertSame(1, ImportBatch::findOrFail($id)->success_count); $this->assertSame($before, $old->fresh()->toArray());
        $this->assertDatabaseHas('class_subjects', ['subject_id' => $this->subject->id, 'faculty_id' => $this->faculty->id, 'review_period_id' => $this->active->id]);
    }

    public function test_import_job_rechecks_period_before_any_row_writes(): void
    {
        $batch = ImportBatch::create(['type' => 'class', 'created_by' => $this->admin->id, 'total_rows' => 1]);
        $this->active->update(['is_active' => false]);
        ImportClassRowsJob::dispatchSync([['1', 'B', 'S18', 'F18']], ['year' => 0, 'block' => 1, 'subjectCode' => 2, 'facultyId' => 3], $batch->id, $this->program->id, $this->active->id);
        $this->assertDatabaseCount('class_subjects', 0); $this->assertDatabaseCount('class_sections', 1);
        $this->assertSame(1, $batch->fresh()->failure_count);
    }

    public function test_unevaluated_historical_schedule_prevents_global_section_rename_or_deletion(): void
    {
        $history = $this->section->classSubjects()->create(['review_period_id' => $this->past->id, 'subject_id' => $this->subject->id, 'faculty_id' => $this->faculty->id]);
        $before = $history->fresh()->toArray();
        $this->put($this->url('update'), $this->payload(['block' => 'Renamed']))->assertSessionHasErrors('block');
        $this->delete($this->url('destroy'), ['termId' => $this->active->id])->assertSessionHasErrors('subjectIds');
        $this->assertSame('A', $this->section->fresh()->block); $this->assertSame($before, $history->fresh()->toArray());
        $this->put($this->url('update'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($before, $history->fresh()->toArray());
    }

    public function test_office_and_student_roles_still_cannot_schedule_classes(): void
    {
        foreach (['office_admin', 'student'] as $role) {
            $this->actingAs($role === 'department_admin' ? $this->admin : User::factory()->create(['role' => $role]));
            $this->post($this->url(), $this->payload(['block' => 'B']))->assertForbidden();
            $this->put($this->url('update'), $this->payload())->assertForbidden();
            $this->postJson($this->importUrl('preview'), ['termId' => $this->active->id])->assertForbidden();
        }
    }
}
