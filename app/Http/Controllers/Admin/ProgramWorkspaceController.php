<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Services\EnrollmentSectionResolver;
use App\Support\ClassScheduleVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramWorkspaceController extends Controller
{
    use ScopesToProgram;

    // "Manage Program" page — its subjects, faculty, classes, and students
    public function show(Request $request, Department $department, Program $program): Response
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        // Explicit historical links remain readable; normal scheduling uses only the active period.
        $terms = ReviewPeriod::orderByDesc('start_date')->get();
        $activeTerm = $terms->firstWhere('is_active', true);
        $term = $terms->firstWhere('id', (int) $request->query('term')) ?? $activeTerm;

        $subjects = $program->subjects()->orderBy('code')->get()->map(fn ($subject) => [
            'id' => $subject->id,
            'code' => $subject->code,
            'description' => $subject->description,
            'year' => (string) $subject->year_level,
            'credits' => $subject->credits,
        ]);

        $faculty = $program->faculty()
            ->with('qualifiedSubjects:subjects.id')
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'facultyId' => $f->faculty_id,
                'name' => $f->name,
                'subjectIds' => $f->qualifiedSubjects->pluck('id'),
                'photoUrl' => $f->photoUrl(),
            ]);

        // Faculty records retain their home program, but Class assignment can use the
        // institution-wide directory. Keep this separate from the program Faculty tab.
        $classFaculty = Faculty::query()
            ->with('program.department')
            ->orderBy('name')
            ->orderBy('faculty_id')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'facultyId' => $f->faculty_id,
                'name' => $f->name,
                'label' => "{$f->name} — {$f->faculty_id} · {$f->program?->department?->code} / {$f->program?->code}",
            ]);

        $classes = $program->classSections()
            ->with(['classSubjects' => fn ($q) => $q->where('review_period_id', $term?->id ?? 0)])
            ->orderBy('year_level')
            ->orderBy('block')
            ->get()
            ->map(fn ($section) => [
                'id' => $section->id,
                'year' => (string) $section->year_level,
                'block' => $section->block,
                'subjectIds' => $section->classSubjects->pluck('subject_id'),
                // one instructor per class, read from the first assignment
                'instructorId' => $section->classSubjects->first()?->faculty_id,
                'assignments' => $section->classSubjects->map(fn ($offering) => [
                    'subjectId' => $offering->subject_id,
                    'instructorId' => $offering->faculty_id,
                ])->values(),
                'scheduleVersion' => $term ? ClassScheduleVersion::for($section, $term->id, $section->classSubjects) : null,
            ]);

        $students = $program->students()
            ->with($activeTerm ? ['classSection', 'enrolledSubjects.program'] : ['classSection'])
            ->orderBy('last_name')
            ->get();

        // No current enrollments can be resolved without an active period. Never build a null pivot value.
        if (! $activeTerm) {
            $students->each(fn (Student $student) => $student->setRelation('enrolledSubjects', collect()));
        }

        $enrolledSubjectIds = $students->flatMap->enrolledSubjects->pluck('id')->unique();
        $offeringsBySubject = app(EnrollmentSectionResolver::class)->offeringsFor($enrolledSubjectIds);

        $students = $students->map(fn (Student $student) => [
            'id' => $student->id,
            'studentId' => $student->student_number,
            'email' => $student->email,
            'firstName' => $student->first_name,
            'middleName' => $student->middle_name,
            'lastName' => $student->last_name,
            'suffix' => $student->suffix,
            'isIrregular' => $student->is_irregular,
            'year' => $student->classSection ? (string) $student->classSection->year_level : null,
            'block' => $student->classSection?->block,
            'enrolledCourses' => $student->is_irregular
                ? $student->enrolledSubjects
                    ->map(fn ($subject) => CourseSearchController::present($subject, $offeringsBySubject[$subject->id] ?? [], $subject->pivot->class_section_id))
                    ->values()
                : [],
        ]);

        return Inertia::render('Admin/Departments/Partials/ManageProgramView', [
            'department' => $department,
            'program' => $program,
            'subjects' => $subjects,
            'faculty' => $faculty,
            'classFaculty' => $classFaculty,
            'classes' => $classes,
            'term' => $term ? ['id' => $term->id, 'label' => $term->label(), 'isActive' => $term->is_active] : null,
            'canScheduleClasses' => $activeTerm && $term?->id === $activeTerm->id,
            'terms' => $terms->map(fn ($t) => ['id' => $t->id, 'label' => $t->label(), 'isActive' => $t->is_active])->values(),
            'students' => $students,
        ]);
    }
}
