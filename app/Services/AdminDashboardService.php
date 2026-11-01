<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Office;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Student;
use App\Support\EnrollmentOfferings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// builds the numbers and the evaluation feed for the admin dashboard, cached
class AdminDashboardService
{
    private const CACHE_TTL = 60; // seconds

    private const FEED_PER_PAGE = 5;

    public function activePeriod(): ?ReviewPeriod
    {
        return ReviewPeriod::where('is_active', true)->first();
    }

    // top stat cards + progress bars, cached per active period
    // $departmentId limits everything to one department
    public function stats(?ReviewPeriod $period, ?int $departmentId = null): array
    {
        $cacheKey = 'admin-dashboard-stats:'.($period?->id ?? 'none').':'.($departmentId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period, $departmentId) {
            $deptPrograms = fn () => Program::where('department_id', $departmentId)->select('id');
            $inDepartment = fn ($q) => $q->when($departmentId, fn ($q) => $q->whereIn('students.program_id', $deptPrograms()));

            $totalStudents = Student::query()->tap($inDepartment)->count();
            $totalFaculty = Faculty::query()
                ->when($departmentId, fn ($q) => $q->whereIn('program_id', $deptPrograms()))
                ->count();
            // own offices + the ones open to everyone
            $totalOffices = Office::query()
                ->when($departmentId, fn ($q) => $q->where(fn ($o) => $o
                    ->where('department_id', $departmentId)->orWhereNull('department_id')))
                ->count();

            // no period set up yet — nothing to report progress on
            if (! $period) {
                return [
                    'totalStudents' => $totalStudents,
                    'totalFaculty' => $totalFaculty,
                    'totalOffices' => $totalOffices,
                    'facultyPending' => 0,
                    'officePending' => 0,
                    'facultyProgress' => 0,
                    'officeProgress' => 0,
                    'isReviewOpen' => false,
                ];
            }

            // regular students count by class section; irregulars enroll
            // per-subject instead, so count them separately
            $expectedFacultyRegular = Student::query()
                ->tap($inDepartment)
                ->where('is_irregular', false)
                ->join('class_subjects', 'class_subjects.class_section_id', '=', 'students.class_section_id')
                ->where('class_subjects.review_period_id', $period->id)
                ->whereNotNull('class_subjects.faculty_id')
                ->count();

            $expectedFacultyIrregular = EnrollmentOfferings::join(DB::table('student_subject_enrollments')
                ->join('students', 'students.id', '=', 'student_subject_enrollments.student_id'))
                ->where('students.is_irregular', true)
                ->when($departmentId, fn ($q) => $q->whereIn('students.program_id', $deptPrograms()))
                ->where('class_subjects.review_period_id', $period->id)
                ->whereNotNull('class_subjects.faculty_id')
                ->count();

            $expectedFaculty = $expectedFacultyRegular + $expectedFacultyIrregular;

            // null department_id = office applies to every department
            $officeCountsByDepartment = Office::whereNotNull('department_id')
                ->selectRaw('department_id, count(*) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id');

            $globalOfficeCount = Office::whereNull('department_id')->count();

            $studentCountsByDepartment = Student::query()
                ->tap($inDepartment)
                ->join('programs', 'programs.id', '=', 'students.program_id')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            $expectedOffice = 0;
            foreach ($studentCountsByDepartment as $studentDepartmentId => $studentCount) {
                $expectedOffice += $studentCount * (($officeCountsByDepartment[$studentDepartmentId] ?? 0) + $globalOfficeCount);
            }

            // counts for the student's department
            $submitted = fn (string $type) => Evaluation::where('review_period_id', $period->id)
                ->where('evaluatee_type', $type)
                ->whereNotNull('submitted_at')
                ->when($departmentId, fn ($q) => $q->whereIn('student_id', Student::query()
                    ->whereIn('program_id', $deptPrograms())
                    ->select('id')))
                ->count();

            $facultySubmitted = $submitted(Evaluation::TYPE_FACULTY);
            $officeSubmitted = $submitted(Evaluation::TYPE_OFFICE);

            return [
                'totalStudents' => $totalStudents,
                'totalFaculty' => $totalFaculty,
                'totalOffices' => $totalOffices,
                'facultyPending' => max($expectedFaculty - $facultySubmitted, 0),
                'officePending' => max($expectedOffice - $officeSubmitted, 0),
                'facultyProgress' => $expectedFaculty > 0 ? (int) round($facultySubmitted / $expectedFaculty * 100) : 0,
                'officeProgress' => $expectedOffice > 0 ? (int) round($officeSubmitted / $expectedOffice * 100) : 0,
                'isReviewOpen' => $period->is_submission_open,
            ];
        });
    }

    // every (student, type) pair still pending, for the "remind all" button — not paginated
    public function pendingReminderTargets(?ReviewPeriod $period, ?int $departmentId, ?int $programId, bool $includeOffices = true): array
    {
        if (! $period) {
            return [];
        }

        $query = Student::query()->with(['classSection', 'enrolledSubjects', 'program']);
        $this->narrowStudents($query, $departmentId, $programId);

        $students = $query->withCount([
            'evaluations as faculty_submitted_count' => fn ($q) => $q
                ->where('review_period_id', $period->id)
                ->where('evaluatee_type', Evaluation::TYPE_FACULTY)
                ->whereNotNull('submitted_at'),
            'evaluations as office_submitted_count' => fn ($q) => $q
                ->where('review_period_id', $period->id)
                ->where('evaluatee_type', Evaluation::TYPE_OFFICE)
                ->whereNotNull('submitted_at'),
        ])->get();

        $officeCountsByDepartment = Office::whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');
        $globalOfficeCount = Office::whereNull('department_id')->count();

        $facultyExpectedBySection = $this->facultyExpectedBySection(
            $students->where('is_irregular', false)->pluck('class_section_id')->filter()->unique(),
            $period?->id,
        );
        $facultyExpectedByStudent = $this->facultyExpectedByStudent($students->where('is_irregular', true)->pluck('id'), $period?->id);

        $targets = [];
        foreach ($students as $student) {
            $facultyExpected = $student->is_irregular
                ? $facultyExpectedByStudent[$student->id] ?? 0
                : $facultyExpectedBySection[$student->class_section_id] ?? 0;

            if ($facultyExpected > 0 && ($student->faculty_submitted_count ?? 0) < $facultyExpected) {
                $targets[] = ['student_id' => $student->id, 'type' => Evaluation::TYPE_FACULTY];
            }

            $officeExpected = ($officeCountsByDepartment[$student->department()?->id] ?? 0) + $globalOfficeCount;

            if ($includeOffices && $officeExpected > 0 && ($student->office_submitted_count ?? 0) < $officeExpected) {
                $targets[] = ['student_id' => $student->id, 'type' => Evaluation::TYPE_OFFICE];
            }
        }

        return $targets;
    }

    // per-department counts + completion %, for the dashboard charts
    public function departmentBreakdown(?ReviewPeriod $period, ?int $departmentId = null): array
    {
        $cacheKey = 'admin-dashboard-departments:'.($period?->id ?? 'none').':'.($departmentId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period, $departmentId) {
            $departments = Department::orderBy('name')
                ->when($departmentId, fn ($q) => $q->whereKey($departmentId))
                ->get(['id', 'code', 'name']);

            $studentCounts = Student::query()
                ->join('programs', 'programs.id', '=', 'students.program_id')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            $officeCounts = Office::whereNotNull('department_id')
                ->selectRaw('department_id, count(*) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id');

            $globalOfficeCount = Office::whereNull('department_id')->count();

            if (! $period) {
                return $departments->map(fn ($department) => [
                    'code' => $department->code,
                    'students' => $studentCounts[$department->id] ?? 0,
                    'facultyProgress' => 0,
                    'officeProgress' => 0,
                ])->values()->all();
            }

            $expectedFacultyRegular = Student::query()
                ->join('programs', 'programs.id', '=', 'students.program_id')
                ->join('class_subjects', 'class_subjects.class_section_id', '=', 'students.class_section_id')
                ->where('students.is_irregular', false)
                ->where('class_subjects.review_period_id', $period->id)
                ->whereNotNull('class_subjects.faculty_id')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            $expectedFacultyIrregular = EnrollmentOfferings::join(DB::table('student_subject_enrollments')
                ->join('students', 'students.id', '=', 'student_subject_enrollments.student_id')
                ->join('programs', 'programs.id', '=', 'students.program_id'))
                ->where('students.is_irregular', true)
                ->where('class_subjects.review_period_id', $period->id)
                ->whereNotNull('class_subjects.faculty_id')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            $facultySubmitted = Evaluation::query()
                ->join('students', 'students.id', '=', 'evaluations.student_id')
                ->join('programs', 'programs.id', '=', 'students.program_id')
                ->where('evaluations.review_period_id', $period->id)
                ->where('evaluations.evaluatee_type', Evaluation::TYPE_FACULTY)
                ->whereNotNull('evaluations.submitted_at')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            $officeSubmitted = Evaluation::query()
                ->join('students', 'students.id', '=', 'evaluations.student_id')
                ->join('programs', 'programs.id', '=', 'students.program_id')
                ->where('evaluations.review_period_id', $period->id)
                ->where('evaluations.evaluatee_type', Evaluation::TYPE_OFFICE)
                ->whereNotNull('evaluations.submitted_at')
                ->selectRaw('programs.department_id, count(*) as total')
                ->groupBy('programs.department_id')
                ->pluck('total', 'department_id');

            return $departments->map(function ($department) use (
                $studentCounts, $officeCounts, $globalOfficeCount,
                $expectedFacultyRegular, $expectedFacultyIrregular, $facultySubmitted, $officeSubmitted,
            ) {
                $id = $department->id;
                $studentCount = $studentCounts[$id] ?? 0;
                $expectedFaculty = ($expectedFacultyRegular[$id] ?? 0) + ($expectedFacultyIrregular[$id] ?? 0);
                $expectedOffice = $studentCount * (($officeCounts[$id] ?? 0) + $globalOfficeCount);

                return [
                    'code' => $department->code,
                    'students' => $studentCount,
                    'facultyProgress' => $expectedFaculty > 0
                        ? (int) round(($facultySubmitted[$id] ?? 0) / $expectedFaculty * 100)
                        : 0,
                    'officeProgress' => $expectedOffice > 0
                        ? (int) round(($officeSubmitted[$id] ?? 0) / $expectedOffice * 100)
                        : 0,
                ];
            })->values()->all();
        });
    }

    // "who's done, who's not" feed, filterable by department/program
    public function feed(?ReviewPeriod $period, ?int $departmentId, ?int $programId, int $page): array
    {
        $cacheKey = sprintf(
            'admin-dashboard-feed:%s:%s:%s:%d',
            $period?->id ?? 'none',
            $departmentId ?? 'all',
            $programId ?? 'all',
            $page,
        );

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period, $departmentId, $programId, $page) {
            $query = Student::query()->with(['classSection', 'program.department']);
            $this->narrowStudents($query, $departmentId, $programId);

            if ($period) {
                $query->withCount([
                    'evaluations as faculty_submitted_count' => fn ($q) => $q
                        ->where('review_period_id', $period->id)
                        ->where('evaluatee_type', Evaluation::TYPE_FACULTY)
                        ->whereNotNull('submitted_at'),
                    'evaluations as office_submitted_count' => fn ($q) => $q
                        ->where('review_period_id', $period->id)
                        ->where('evaluatee_type', Evaluation::TYPE_OFFICE)
                        ->whereNotNull('submitted_at'),
                ]);
            }

            $paginator = $query->orderBy('last_name')->paginate(self::FEED_PER_PAGE, page: $page);

            // same deal — global offices (null dept) count for everyone
            $officeCountsByDepartment = Office::whereNotNull('department_id')
                ->selectRaw('department_id, count(*) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id');

            $globalOfficeCount = Office::whereNull('department_id')->count();

            $students = $paginator->getCollection();

            $facultyExpectedBySection = $this->facultyExpectedBySection(
                $students->where('is_irregular', false)->pluck('class_section_id')->filter()->unique(),
                $period?->id,
            );

            $facultyExpectedByStudent = $this->facultyExpectedByStudent($students->where('is_irregular', true)->pluck('id'), $period?->id);

            $students = $students->map(function (Student $student) use ($facultyExpectedBySection, $facultyExpectedByStudent, $officeCountsByDepartment, $globalOfficeCount) {
                $facultyExpected = $student->is_irregular
                    ? $facultyExpectedByStudent[$student->id] ?? 0
                    : $facultyExpectedBySection[$student->class_section_id] ?? 0;
                $facultySubmitted = $student->faculty_submitted_count ?? 0;

                $officeExpected = ($officeCountsByDepartment[$student->department()?->id] ?? 0) + $globalOfficeCount;
                $officeSubmitted = $student->office_submitted_count ?? 0;

                return [
                    'id' => $student->id,
                    'name' => $student->fullName(),
                    'studentId' => $student->student_number,
                    'submissions' => [
                        [
                            'type' => Evaluation::TYPE_FACULTY,
                            'label' => "{$facultySubmitted}/{$facultyExpected} Faculty Members",
                            'status' => $facultyExpected > 0 && $facultySubmitted >= $facultyExpected ? 'completed' : 'pending',
                        ],
                        [
                            'type' => Evaluation::TYPE_OFFICE,
                            'label' => "{$officeSubmitted}/{$officeExpected} Offices",
                            'status' => $officeExpected > 0 && $officeSubmitted >= $officeExpected ? 'completed' : 'pending',
                        ],
                    ],
                ];
            })->values();

            return [
                'data' => $students,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        });
    }

    // both filters apply together
    private function narrowStudents($query, ?int $departmentId, ?int $programId): void
    {
        $query
            ->when($departmentId, fn ($q) => $q->whereIn(
                'students.program_id', Program::where('department_id', $departmentId)->select('id'),
            ))
            ->when($programId, fn ($q) => $q->where('students.program_id', $programId));
    }

    // how many taught, instructor-assigned subjects each class section has
    private function facultyExpectedBySection($sectionIds, ?int $periodId): array
    {
        if ($sectionIds->isEmpty()) {
            return [];
        }

        return ClassSubject::whereIn('class_section_id', $sectionIds)
            ->where('review_period_id', $periodId ?? 0)
            ->whereNotNull('faculty_id')
            ->selectRaw('class_section_id, count(*) as total')
            ->groupBy('class_section_id')
            ->pluck('total', 'class_section_id')
            ->all();
    }

    // offerings with an instructor that each irregular student is enrolled in
    private function facultyExpectedByStudent($studentIds, ?int $periodId): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        return EnrollmentOfferings::join(DB::table('student_subject_enrollments'))
            ->whereIn('student_subject_enrollments.student_id', $studentIds)
            ->where('class_subjects.review_period_id', $periodId ?? 0)
            ->whereNotNull('class_subjects.faculty_id')
            ->selectRaw('student_subject_enrollments.student_id, count(*) as total')
            ->groupBy('student_subject_enrollments.student_id')
            ->pluck('total', 'student_id')
            ->all();
    }

    // filter dropdown options for the feed
    public function filterOptions(?int $departmentId = null): array
    {
        return Cache::remember('admin-dashboard-filters:'.($departmentId ?? 'all'), self::CACHE_TTL, fn () => [
            'departments' => Department::orderBy('name')
                ->when($departmentId, fn ($q) => $q->whereKey($departmentId))
                ->get(['id', 'code', 'name']),
            'programs' => Program::orderBy('name')
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->get(['id', 'code', 'name', 'department_id']),
        ]);
    }
}
