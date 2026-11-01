<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacultyQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Program $program;
    private Faculty $faculty;
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEPARTMENT_ADMIN]));
        $this->department = Department::create(['code' => 'CS', 'name' => 'Computer Studies']);
        $this->program = $this->department->programs()->create(['code' => 'BSIT', 'name' => 'IT']);
        $this->subject = $this->program->subjects()->create(['code' => 'IT101', 'description' => 'Intro', 'year_level' => 1]);
        $this->faculty = $this->program->faculty()->create(['faculty_id' => 'F1', 'name' => 'Ada']);
        $this->faculty->qualifiedSubjects()->attach($this->subject->id);
    }

    private function base(): array
    {
        return [$this->department, $this->program];
    }

    private function import(string $csv, array $mapping): ImportBatch
    {
        $token = $this->postJson(route('programs.faculty.import.preview', $this->base()), [
            'file' => UploadedFile::fake()->createWithContent('faculty.csv', $csv),
        ])->assertOk()->json('token');
        $id = $this->postJson(route('programs.faculty.import.store', $this->base()), [
            'token' => $token, 'mapping' => $mapping, 'startRow' => 2,
        ])->assertOk()->json('batchId');

        return ImportBatch::findOrFail($id);
    }

    public function test_new_manual_faculty_does_not_require_qualified_subjects(): void
    {
        $this->post(route('programs.faculty.store', $this->base()), ['facultyId' => 'F2', 'name' => 'Bo'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $faculty = Faculty::where('faculty_id', 'F2')->firstOrFail();
        $this->assertSame(0, $faculty->qualifiedSubjects()->count());
        $this->assertSame([$this->subject->id], $this->faculty->qualifiedSubjects()->pluck('subjects.id')->all());
    }

    public function test_omitted_qualification_fields_preserve_existing_pivot_rows_and_photo(): void
    {
        $this->faculty->update(['photo_path' => 'faculty-photos/existing.png']);
        Storage::disk('public')->put('faculty-photos/existing.png', 'synthetic image');
        $before = DB::table('faculty_subject')->where('faculty_id', $this->faculty->id)->get()->toJson();
        $this->put(route('programs.faculty.update', [...$this->base(), $this->faculty]), [
            'facultyId' => 'F1', 'name' => 'Ada Updated',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('faculty_subject')->where('faculty_id', $this->faculty->id)->get()->toJson());
        $this->assertSame('Ada Updated', $this->faculty->fresh()->name);
        $this->assertSame('faculty-photos/existing.png', $this->faculty->fresh()->photo_path);
        Storage::disk('public')->assertExists('faculty-photos/existing.png');
    }

    public function test_photo_upload_still_works_without_qualification_fields(): void
    {
        $this->post(route('programs.faculty.update', [...$this->base(), $this->faculty]), [
            '_method' => 'put', 'facultyId' => 'F1', 'name' => 'Ada',
            'photo' => UploadedFile::fake()->image('faculty.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $path = $this->faculty->fresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame([$this->subject->id], $this->faculty->qualifiedSubjects()->pluck('subjects.id')->all());
    }

    public function test_explicit_legacy_qualifications_remain_supported_and_program_validated(): void
    {
        $other = $this->department->programs()->create(['code' => 'OTHER', 'name' => 'Other']);
        $foreign = $other->subjects()->create(['code' => 'X1', 'description' => 'Other', 'year_level' => 1]);
        $url = route('programs.faculty.update', [...$this->base(), $this->faculty]);
        $this->put($url, ['facultyId' => 'F1', 'name' => 'Ada', 'subjectIds' => [$foreign->id]])->assertSessionHasErrors('subjectIds.0');
        $this->assertSame([$this->subject->id], $this->faculty->qualifiedSubjects()->pluck('subjects.id')->all());
        $this->put($url, ['facultyId' => 'F1', 'name' => 'Ada', 'subjectIds' => [$this->subject->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$this->subject->id], $this->faculty->qualifiedSubjects()->pluck('subjects.id')->all());
    }

    public function test_simplified_faculty_import_creates_new_faculty_without_erasing_existing_qualifications(): void
    {
        $before = DB::table('faculty_subject')->get()->toJson();
        $batch = $this->import("id,name\nF2,Bo\nF1,Changed\n", ['facultyId' => 0, 'name' => 1]);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame(1, $batch->failure_count);
        $this->assertSame('Ada', $this->faculty->fresh()->name);
        $this->assertSame($before, DB::table('faculty_subject')->get()->toJson());
        $this->assertSame(0, Faculty::where('faculty_id', 'F2')->firstOrFail()->qualifiedSubjects()->count());
    }

    public function test_legacy_import_subject_mapping_still_populates_only_the_new_facultys_qualifications(): void
    {
        $batch = $this->import("id,name,subjects\nF2,Bo,IT101\n", ['facultyId' => 0, 'name' => 1, 'subjectCodes' => 2]);
        $this->assertSame(1, $batch->success_count);
        foreach ([$this->faculty, Faculty::where('faculty_id', 'F2')->firstOrFail()] as $faculty) {
            $this->assertSame([$this->subject->id], $faculty->qualifiedSubjects()->pluck('subjects.id')->all());
        }
    }
}
