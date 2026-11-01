<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\ReviewPeriod;
use App\Models\Subject;
use App\Services\EnrollmentSectionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseSearchController extends Controller
{
    private const MAX_RESULTS = 30;

    // system-wide course search for enrolling irregular students
    public function search(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);
        $search = trim($filters['q'] ?? '');
        $year = $filters['year'] ?? null;
        $departmentId = $filters['department_id'] ?? null;
        $programId = $filters['program_id'] ?? null;

        $subjects = Subject::query()
            ->with('program.department')
            ->when($year, fn ($q) => $q->where('year_level', $year))
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'program',
                fn ($p) => $p->where('department_id', $departmentId),
            ))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('program', fn ($p) => $p->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('classSubjects', fn ($cs) => $cs
                        ->where('review_period_id', ReviewPeriod::activeId() ?? 0)
                        ->whereHas('faculty', fn ($f) => $f->where('name', 'like', "%{$search}%")));
            }))
            ->orderBy('code')
            ->limit(self::MAX_RESULTS)
            ->get();

        $offerings = app(EnrollmentSectionResolver::class)->offeringsFor($subjects->pluck('id'));

        $results = $subjects->map(fn (Subject $subject) => self::present($subject, $offerings[$subject->id] ?? []))->values();

        return response()->json(['data' => $results]);
    }

    // options for the department/program filter dropdowns
    public function filters(): JsonResponse
    {
        return response()->json([
            'departments' => Department::orderBy('name')->get(['id', 'code', 'name']),
            'programs' => Program::orderBy('name')->get(['id', 'department_id', 'code', 'name']),
        ]);
    }

    public static function present(Subject $subject, $offerings = [], ?int $classSectionId = null): array
    {
        $offerings = collect($offerings);
        // one offering: pre-select it
        $classSectionId ??= $offerings->count() === 1 ? $offerings->first()['sectionId'] : null;

        return [
            'id' => $subject->id,
            'subjectCode' => $subject->code,
            'subjectName' => $subject->description,
            'programCode' => $subject->program->code,
            'year' => (string) $subject->year_level,
            'offerings' => $offerings->values(),
            'classSectionId' => $classSectionId,
            'facultyName' => $offerings->firstWhere('sectionId', $classSectionId)['facultyName'] ?? null,
        ];
    }
}
