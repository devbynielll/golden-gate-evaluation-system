<?php

namespace Tests\Feature;

use App\Jobs\GenerateEvaluationNotifications;
use App\Models\{ClassSubject, CriteriaCategory, Department, Evaluation, Office, Program, ReviewPeriod, Student, Subject, User};
use App\Services\EvaluationNotificationService;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Tests\TestCase;

class EvaluationNotificationTest extends TestCase
{
    private ReviewPeriod $period;
    private Office $office;
    private Office $other;
    private Program $program;
    private ClassSubject $offering;
    private array $questions;
    private User $super;
    private User $department;
    private User $admin;
    private User $foreignAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertFalse(Schema::hasTable('users'));
        // Real commits are required here; discard this in-memory connection at teardown.
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->withoutVite(); Mail::fake();
        $department = Department::create(['code' => 'SYN', 'name' => 'Synthetic Department']);
        $this->program = $department->programs()->create(['code' => 'SYN', 'name' => 'Synthetic Program']);
        $this->office = Office::create(['code' => 'REG', 'name' => 'Synthetic Registrar']);
        $this->other = Office::create(['code' => 'LIB', 'name' => 'Synthetic Library']);
        $this->period = ReviewPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => now()->subMonth(), 'end_date' => now()->subDay(), 'is_active' => true, 'is_submission_open' => true]);
        $section = $this->program->classSections()->create(['year_level' => 1, 'block' => 'A']);
        $subject = Subject::create(['program_id' => $this->program->id, 'code' => 'SYN101', 'description' => 'Synthetic Subject', 'year_level' => 1]);
        $faculty = $this->program->faculty()->create(['faculty_id' => 'F-SYN', 'name' => 'Synthetic Faculty']);
        $this->offering = ClassSubject::create(['class_section_id' => $section->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id, 'review_period_id' => $this->period->id]);
        foreach (['faculty', 'office'] as $kind) {
            $category = CriteriaCategory::create(['evaluatee_type' => $kind, 'section_number' => 1, 'name' => 'Synthetic '.$kind]);
            $this->questions[$kind] = $category->questions()->create(['text' => 'Stored '.$kind.' question', 'order' => 1])->id;
        }
        $this->super = User::factory()->create(['role' => 'super_admin']);
        $this->department = User::factory()->create(['role' => 'department_admin']);
        $this->admin = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->office->id]);
        $this->foreignAdmin = User::factory()->create(['role' => 'office_admin', 'office_id' => $this->other->id]);
    }


    private function student(): Student
    {
        $user = User::factory()->create(['role' => 'student']);
        return $this->program->students()->create(['user_id' => $user->id, 'student_number' => 'S'.$user->id, 'email' => $user->email, 'first_name' => 'SECRET NAME', 'last_name' => 'SECRET STUDENT', 'class_section_id' => $this->offering->class_section_id]);
    }

    private function record(Student $student, string $kind = 'office', ?Office $office = null, bool $answered = true): Evaluation
    {
        return DB::transaction(function () use ($student, $kind, $office, $answered) {
            $evaluation = Evaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id,
                'evaluatee_type' => $kind, 'class_subject_id' => $kind === 'faculty' ? $this->offering->id : null,
                'office_id' => $kind === 'office' ? ($office ?? $this->office)->id : null,
                'comments' => 'SECRET FEEDBACK', 'submitted_at' => now()]);
            if ($answered) $evaluation->answers()->create(['criteria_question_id' => $this->questions[$kind], 'rating' => 4]);
            return $evaluation;
        });
    }

    private function populate(int $count = 5): void
    {
        for ($i = 0; $i < $count; $i++) {
            $student = $this->student();
            foreach (['faculty', 'office'] as $kind) $this->record($student, $kind);
            $this->record($student, office: $this->other);
        }
    }

    private function generate(): void { (new GenerateEvaluationNotifications($this->period->id))->handle(app(EvaluationNotificationService::class)); }
    private function inbox(User $user): array { return $this->actingAs($user)->getJson(route('admin-notifications.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json(); }
    private function setRead(string $id, bool $read = true) { return $this->patchJson(route('admin-notifications.update', $id), ['read' => $read]); }

    public function test_recipient_scopes_safe_content_links_and_standard_laravel_storage(): void
    {
        $this->populate(); $this->generate();
        $this->assertDatabaseCount('notifications', 0); // Date expiry alone does not release.
        $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertDatabaseCount('notifications', 6);
        foreach ([[$this->super, 3], [$this->department, 1], [$this->admin, 1], [$this->foreignAdmin, 1]] as [$user, $expected]) {
            $inbox = $this->inbox($user); $this->assertCount($expected, $inbox['items']); $this->assertSame($expected, $inbox['unread']);
            foreach ($inbox['items'] as $item) {
                $this->assertSame(['id', 'kind', 'read', 'title', 'subject', 'period', 'message', 'url'], array_keys($item));
                $this->assertSame('1st Semester | 2026-2027', $item['period']);
                $this->assertStringContainsString('period_id='.$this->period->id, $item['url']);
                $this->get($item['url'])->assertOk();
            }
            foreach (['SECRET', 'student_id', 'submitted_at', 'created_at', 'read_at', 'rating', 'responses', 'feedback', 'Staff'] as $secret) $this->assertStringNotContainsString($secret, json_encode($inbox));
        }
        $this->assertSame(3, $this->super->notifications()->count());
        $this->assertSame('faculty', $this->inbox($this->department)['items'][0]['kind']);
        $this->assertStringContainsString('Registrar', $this->inbox($this->admin)['items'][0]['subject']);
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_idempotence_read_unread_scoping_reopening_and_reclosure(): void
    {
        $this->populate(); $this->period->update(['is_submission_open' => false]);
        $this->generate(); $this->generate(); $this->assertDatabaseCount('notifications', 6);
        $own = $this->inbox($this->admin)['items'][0]['id'];
        $this->setRead($own)->assertOk(); $this->assertSame(0, $this->inbox($this->admin)['unread']);
        $this->generate(); $this->assertSame(0, $this->inbox($this->admin)['unread']);
        $this->setRead($own, false)->assertOk(); $this->assertSame(1, $this->inbox($this->admin)['unread']);
        $this->setRead($own)->assertOk();
        $this->period->update(['is_submission_open' => true]);
        foreach ([$this->super, $this->department, $this->admin] as $user) $this->assertSame(['unread' => 0, 'items' => [], 'page' => 1, 'lastPage' => 1], $this->inbox($user));
        $this->setRead($own, false)->assertNotFound(); $this->generate(); $this->assertDatabaseCount('notifications', 6);
        $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertSame(0, $this->inbox($this->admin)['unread']);
        $this->assertDatabaseCount('notifications', 6);
    }

    public function test_forged_recipients_offices_roles_and_changed_assignments_are_denied(): void
    {
        $this->populate(); $this->period->update(['is_submission_open' => false]); $this->generate();
        $own = $this->inbox($this->admin)['items'][0]['id'];
        $foreign = $this->inbox($this->foreignAdmin)['items'][0]['id'];
        $faculty = $this->inbox($this->department)['items'][0]['id'];
        $this->actingAs($this->admin); $this->setRead($foreign)->assertNotFound(); $this->setRead($faculty)->assertNotFound();
        $forged = $this->getJson(route('admin-notifications.index', ['office_id' => $this->other->id, 'user_id' => $this->super->id]))->assertOk()->json();
        $this->assertSame([$own], array_column($forged['items'], 'id'));
        $this->foreignAdmin->update(['office_id' => null]);
        $this->admin->update(['office_id' => $this->other->id]);
        $this->assertSame(0, $this->inbox($this->admin)['unread']); $this->setRead($own)->assertNotFound();
        $this->generate(); $new = $this->inbox($this->admin); $this->assertSame(1, $new['unread']);
        $this->assertStringContainsString('Library', $new['items'][0]['subject']);
        $this->admin->update(['office_id' => null]); $this->assertSame([], $this->inbox($this->admin)['items']);
        $this->department->update(['role' => 'super_admin']);
        $this->admin->update(['role' => 'department_admin']);
        $this->generate(); $this->assertSame('faculty', $this->inbox($this->admin)['items'][0]['kind']);
        $this->setRead($own)->assertNotFound();
        $student = $this->student();
        $this->actingAs($student->user)->getJson(route('admin-notifications.index'))->assertForbidden();
        $this->setRead($own)->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create(['role' => 'super_admin']))->getJson(route('admin-notifications.index'))->assertForbidden();
        auth()->logout(); $this->getJson(route('admin-notifications.index'))->assertUnauthorized();
    }

    public function test_small_groups_distinct_students_incomplete_answers_and_live_gate_do_not_leak(): void
    {
        $this->populate(4); $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertDatabaseCount('notifications', 0);
        foreach ([$this->super, $this->department, $this->admin] as $user) $this->assertSame(0, $this->inbox($user)['unread']);
        $student = $this->student();
        $incomplete = $this->record($student, answered: false); $this->generate(); $this->assertDatabaseCount('notifications', 0);
        $incomplete->answers()->create(['criteria_question_id' => $this->questions['office'], 'rating' => 1]); $this->generate();
        $this->assertDatabaseCount('notifications', 2); // Only eligible office group; faculty and other office stay below threshold.
        $id = $this->inbox($this->admin)['items'][0]['id'];
        $incomplete->answers()->delete();
        $this->assertSame(0, $this->inbox($this->admin)['unread']); $this->setRead($id)->assertNotFound();
        $this->assertDatabaseCount('notifications', 2); // Hide, do not delete history/read states.
    }

    public function test_successful_failed_and_rolled_back_submissions_only_generate_after_official_closure(): void
    {
        $student = $this->student();
        $url = route('evaluation.office.store', $this->office);
        $this->actingAs($student->user)->post($url, ['ratings' => [$this->questions['office'] => 5]])->assertSessionHasErrors();
        $this->assertDatabaseCount('evaluations', 0); $this->assertDatabaseCount('notifications', 0);
        $this->post($url, ['ratings' => [$this->questions['office'] => 4]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifications', 0);
        $this->post($url, ['ratings' => [$this->questions['office'] => 2]])->assertRedirect();
        $this->assertDatabaseCount('evaluations', 1);
        for ($i = 0; $i < 3; $i++) $this->record($this->student());
        try { DB::transaction(function () { $this->record($this->student()); throw new \RuntimeException('rollback'); }); } catch (\RuntimeException) {}
        $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertDatabaseCount('notifications', 0);
        $this->period->update(['is_submission_open' => true]);
        $this->record($this->student());
        $this->actingAs($this->super)->patch(route('review-periods.toggle-submission', $this->period))->assertRedirect();
        $this->assertDatabaseCount('notifications', 2); // Actual sync queue after commit.
        $this->assertFalse($this->period->fresh()->is_submission_open);
    }

    public function test_queue_waits_for_commit_rollback_discards_job_and_reopened_job_is_harmless(): void
    {
        config(['queue.default' => 'database']);
        $this->populate();
        DB::beginTransaction();
        $this->actingAs($this->super)->patch(route('review-periods.toggle-submission', $this->period))->assertRedirect();
        $this->assertDatabaseCount('jobs', 0); $this->assertDatabaseCount('notifications', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0); $this->assertTrue($this->period->fresh()->is_submission_open);
        DB::beginTransaction();
        $this->patch(route('review-periods.toggle-submission', $this->period))->assertRedirect();
        $this->assertDatabaseCount('jobs', 0); DB::commit();
        $this->assertDatabaseCount('jobs', 1); $this->assertDatabaseCount('notifications', 0);
        $this->period->refresh()->update(['is_submission_open' => true]); $this->generate(); $this->assertDatabaseCount('notifications', 0);
        $this->period->update(['is_submission_open' => false]); $this->generate(); $this->generate(); $this->assertDatabaseCount('notifications', 6);
    }

    public function test_pagination_errors_read_validation_and_migration_rollback_safeguards(): void
    {
        $migration = require database_path('migrations/2026_11_19_000003_create_evaluation_notifications_table.php');
        $migration->down(); $migration->up(); $this->assertTrue(Schema::hasTable('notifications'));
        $this->populate(); $this->period->update(['is_submission_open' => false]); $this->generate();
        $first = $this->period;
        for ($i = 0; $i < 4; $i++) {
            $this->period = ReviewPeriod::create(['academic_year' => (2027+$i).'-'.(2028+$i), 'semester' => '1st Semester', 'start_date' => now(), 'end_date' => now()->addMonth(), 'is_submission_open' => false]);
            $this->populate(); $this->generate();
        }
        $inbox = $this->inbox($this->super); $this->assertSame(15, $inbox['unread']); $this->assertCount(10, $inbox['items']);
        $page = $this->getJson(route('admin-notifications.index', ['page' => 2]))->assertOk()->json(); $this->assertCount(5, $page['items']);
        $this->getJson(route('admin-notifications.index', ['page' => 0]))->assertUnprocessable();
        $this->patchJson(route('admin-notifications.update', $inbox['items'][0]['id']), ['read' => 'bad'])->assertUnprocessable();
        try { $migration->down(); $this->fail('Populated rollback must refuse'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('history exists', $e->getMessage()); }
        $this->assertDatabaseCount('notifications', 30);
    }
    public function test_staff_activity_never_creates_legacy_notifications_or_bypasses_staff_privacy(): void
    {
        $member = $this->office->staff()->create(['name' => 'Synthetic staff', 'position' => 'Assistant']);
        $category = \App\Models\StaffCriteriaCategory::create(['section_number' => 1, 'name' => 'Staff service']);
        $question = $category->questions()->create(['text' => 'Private staff question', 'order' => 1]);
        for ($i = 0; $i < 5; $i++) {
            $student = $this->student();
            DB::transaction(function () use ($student, $member, $category, $question) {
                $evaluation = \App\Models\StaffEvaluation::create(['student_id' => $student->id, 'review_period_id' => $this->period->id,
                    'office_staff_id' => $member->id, 'office_id' => $this->office->id, 'staff_name' => $member->name, 'staff_position' => $member->position,
                    'office_code' => $this->office->code, 'office_name' => $this->office->name, 'academic_year' => $this->period->academic_year,
                    'semester' => $this->period->semester, 'submitted_at' => now(), 'comments' => 'SECRET staff feedback']);
                $question->freezeForHistory();
                $evaluation->answers()->create(['staff_criteria_question_id' => $question->id, 'staff_criteria_category_id' => $category->id,
                    'category_name' => $category->name, 'section_number' => 1, 'is_office_specific' => false, 'question_text' => $question->text, 'question_order' => 1, 'rating' => 4]);
            });
            if ($i === 3) {
                $this->period->update(['is_submission_open' => false]); $this->generate();
                $row = app(\App\Services\StaffReportService::class)->rows($this->office->id, $this->period->id, null)->first();
                $this->assertNull($row['responses']); $this->assertNull($row['rating']);
                $this->period->update(['is_submission_open' => true]);
            }
        }
        $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertDatabaseCount('notifications', 0); $this->assertDatabaseCount('evaluations', 0);
        $row = app(\App\Services\StaffReportService::class)->rows($this->office->id, $this->period->id, null)->first();
        $this->assertSame(5, $row['responses']); $this->assertStringNotContainsString('SECRET', json_encode($row));
        $this->period->update(['is_submission_open' => true]);
        $this->assertNull(app(\App\Services\StaffReportService::class)->rows($this->office->id, $this->period->id, null)->first()['rating']);
        $this->assertSame([], $this->inbox($this->super)['items']);
    }

    public function test_multiple_faculty_evaluations_per_student_do_not_inflate_privacy_threshold(): void
    {
        $students = collect(range(1, 4))->map(fn () => $this->student());
        foreach ($students as $student) $this->record($student, 'faculty');
        $subject = Subject::create(['program_id' => $this->program->id, 'code' => 'SYN102', 'description' => 'Another subject', 'year_level' => 1]);
        $this->offering = ClassSubject::create(['class_section_id' => $this->offering->class_section_id, 'subject_id' => $subject->id,
            'faculty_id' => $this->offering->faculty_id, 'review_period_id' => $this->period->id]);
        foreach ($students as $student) $this->record($student, 'faculty');
        $this->period->update(['is_submission_open' => false]); $this->generate();
        $this->assertDatabaseCount('evaluations', 8); $this->assertDatabaseCount('notifications', 0);
        $this->record($this->student(), 'faculty'); $this->generate();
        $this->assertSame(1, $this->inbox($this->department)['unread']);
        $this->assertSame(0, $this->inbox($this->admin)['unread']);
    }

    public function test_scheduled_reconciliation_repairs_missed_dispatch_without_resetting_reads(): void
    {
        $this->populate(); $this->period->update(['is_submission_open' => false]);
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'evaluation-notification-reconciliation');
        $this->assertNotNull($event); $this->assertSame('0 * * * *', $event->expression);
        $this->assertDatabaseCount('notifications', 0);
        $event->run($this->app);
        $this->assertDatabaseCount('notifications', 6);
        $id = $this->inbox($this->admin)['items'][0]['id']; $this->setRead($id)->assertOk();
        $event->run($this->app);
        $this->assertDatabaseCount('notifications', 6); $this->assertSame(0, $this->inbox($this->admin)['unread']);
    }

}
