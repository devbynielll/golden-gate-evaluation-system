<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectCreditsTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $this->department->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
    }

    public function test_subjects_can_be_created_without_credits_by_both_authorized_roles(): void
    {
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_DEPARTMENT_ADMIN] as $index => $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('programs.subjects.store', [$this->department, $this->program]), [
                    'code' => 'IT10'.$index, 'description' => 'Intro', 'year' => 1,
                ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('subjects', [
                'program_id' => $this->program->id, 'code' => 'IT10'.$index,
                'description' => 'Intro', 'year_level' => 1, 'credits' => null,
            ]);
        }
    }

    public function test_subject_edits_preserve_stored_credits_when_the_field_is_omitted(): void
    {
        $subject = $this->program->subjects()->create([
            'code' => 'IT101', 'description' => 'Intro', 'year_level' => 1, 'credits' => 3,
        ]);

        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_DEPARTMENT_ADMIN] as $index => $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('programs.subjects.update', [$this->department, $this->program, $subject]), [
                    'code' => 'IT101', 'description' => 'Updated '.$index, 'year' => 2,
                ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('subjects', [
                'id' => $subject->id, 'description' => 'Updated '.$index, 'year_level' => 2, 'credits' => 3,
            ]);
        }
    }

    public function test_legacy_requests_can_still_create_and_explicitly_update_or_clear_credits(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $payload = ['code' => 'IT101', 'description' => 'Intro', 'year' => 1];

        $this->post(route('programs.subjects.store', [$this->department, $this->program]), [
            ...$payload, 'credits' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'IT101')->firstOrFail();
        $this->assertSame(3, $subject->credits);

        foreach ([0, 4, null] as $credits) {
            $this->put(route('programs.subjects.update', [$this->department, $this->program, $subject]), [
                ...$payload, 'credits' => $credits,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'credits' => $credits]);
        }
    }

    public function test_invalid_legacy_credits_do_not_change_the_subject(): void
    {
        $subject = $this->program->subjects()->create([
            'code' => 'IT101', 'description' => 'Intro', 'year_level' => 1, 'credits' => 3,
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]))
            ->put(route('programs.subjects.update', [$this->department, $this->program, $subject]), [
                'code' => 'IT101', 'description' => 'Changed', 'year' => 2, 'credits' => 13,
            ])->assertSessionHasErrors('credits');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id, 'description' => 'Intro', 'year_level' => 1, 'credits' => 3,
        ]);
    }
}
