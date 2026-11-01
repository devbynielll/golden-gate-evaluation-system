<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\CriteriaCategory;
use App\Models\Evaluation;
use App\Models\Office;
use App\Models\ReviewPeriod;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

// builds the evaluation cards + progress for a student's evaluation page
class StudentEvaluationService
{
    private const CRITERIA_CACHE_TTL = 300;

    private const CACHE_TTL = 60;

    private const PER_PAGE = 6;

    public function activePeriod(): ?ReviewPeriod
    {
        return ReviewPeriod::where('is_active', true)->first();
    }

    public function criteria(string $type, ?int $officeId = null): array
    {
        return Cache::remember("criteria:{$type}:".($officeId ?? 'shared'), self::CRITERIA_CACHE_TTL, fn () => CriteriaCategory::forQuestionnaire($type, $officeId)
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->orderBy('section_number')
            ->orderBy('scope_key')
            ->orderBy('id')
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'section' => (string) $category->section_number,
                'questions' => $category->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'text' => $q->text,
                ]),
            ])
            ->all());
    }

    public function board(Student $student, ?ReviewPeriod $period, int $facultyPage, int $officePage): array
    {
        $cacheKey = "student-evaluation:{$student->id}:".($period?->id ?? 'none').":{$facultyPage}:{$officePage}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($student, $period, $facultyPage, $officePage) {
            $facultyCriteria = $this->criteria(CriteriaCategory::TYPE_FACULTY);

            $assignmentsQuery = $this->assignmentsQuery($student, $period)
                ->whereNotNull('faculty_id')
                ->with(['subject', 'faculty']);

            $totalAssignments = (clone $assignmentsQuery)->count();
            $eligibleIds = (clone $assignmentsQuery)->pluck('class_subjects.id');

            // own department's offices + offices open to everyone (null dept)
            $departmentId = $student->department()?->id;
            $officesQuery = Office::where(fn ($q) => $q
                ->where('department_id', $departmentId)
                ->orWhereNull('department_id'));

            $totalOffices = (clone $officesQuery)->count();

            $submittedFacultyIds = $this->submittedIds($student, $period, Evaluation::TYPE_FACULTY, 'class_subject_id');
            $submittedOfficeIds = $this->submittedIds($student, $period, Evaluation::TYPE_OFFICE, 'office_id');

            $facultyPaginator = $assignmentsQuery->paginate(self::PER_PAGE, page: $facultyPage);
            $officePaginator = $officesQuery->orderBy('name')->paginate(self::PER_PAGE, page: $officePage);

            return [
                'faculties' => [
                    'data' => $facultyPaginator->getCollection()->map(fn ($cs) => [
                        'id' => $cs->id,
                        'cardTitle' => "Prof. {$cs->faculty->name}",
                        'subtitle' => "{$cs->subject->code} | {$cs->subject->description}",
                        'completed' => $submittedFacultyIds->contains($cs->id),
                        'categories' => $facultyCriteria,
                    ])->values(),
                    'meta' => $this->meta($facultyPaginator),
                ],
                'offices' => [
                    'data' => $officePaginator->getCollection()->map(fn ($office) => [
                        'id' => $office->id,
                        'name' => $office->name,
                        'completed' => $submittedOfficeIds->contains($office->id),
                        'categories' => $this->criteria(CriteriaCategory::TYPE_OFFICE, $office->id),
                    ])->values(),
                    'meta' => $this->meta($officePaginator),
                ],
                'progress' => [
                    // only count what's still eligible
                    'completed' => $submittedFacultyIds->intersect($eligibleIds)->count() + $submittedOfficeIds->count(),
                    'total' => $totalAssignments + $totalOffices,
                ],
                'warnings' => $this->warnings($student, $period),
            ];
        });
    }

    private function assignmentsQuery(Student $student, ?ReviewPeriod $period = null)
    {
        return $student->offerings($period?->id ?? ReviewPeriod::activeId());
    }

    // explains why the list is empty
    public function warnings(Student $student, ?ReviewPeriod $period): array
    {
        $warnings = [];

        if (! $period) {
            $warnings[] = 'There is no active review period yet.';

            return $warnings;
        }

        $termId = $period->id;
        $term = $period->label();
        if (! ClassSubject::where('review_period_id', $termId)->exists()) {
            $warnings[] = "No classes have been scheduled for {$term} yet.";

            return $warnings;
        }

        if ($student->is_irregular) {
            $enrolled = $student->enrolledSubjects($termId)->get(['subjects.id', 'subjects.code']);
            if ($enrolled->isEmpty()) {
                $warnings[] = "No enrolled subjects are recorded for you in {$term}. Please ask the registrar to add them.";

                return $warnings;
            }
            $offered = ClassSubject::where('review_period_id', $termId)->whereIn('subject_id', $enrolled->pluck('id'))->pluck('subject_id');
            $noOffering = $enrolled->whereNotIn('id', $offered)->pluck('code');
            if ($noOffering->isNotEmpty()) {
                $warnings[] = 'No class is scheduled yet for: '.$noOffering->implode(', ').'.';
            }

            // several blocks and none recorded, don't guess
            $offeringCounts = ClassSubject::where('review_period_id', $termId)->whereIn('subject_id', $enrolled->pluck('id'))
                ->selectRaw('subject_id, count(*) as total')->groupBy('subject_id')->pluck('total', 'subject_id');
            $noBlock = $student->enrolledSubjects($termId)->wherePivotNull('class_section_id')->get(['subjects.id', 'subjects.code'])
                ->filter(fn ($s) => ($offeringCounts[$s->id] ?? 0) > 1)->pluck('code');
            if ($noBlock->isNotEmpty()) {
                $warnings[] = 'Your block is not recorded for: '.$noBlock->implode(', ').'. Please ask the registrar to set it.';
            }
        } elseif (! $student->class_section_id) {
            $warnings[] = 'No year/block is recorded for you yet. Please ask the registrar to update your record.';

            return $warnings;
        } elseif (! ClassSubject::where('review_period_id', $termId)->where('class_section_id', $student->class_section_id)->exists()) {
            $warnings[] = 'No subjects have been set up for your block yet.';

            return $warnings;
        }

        $noInstructor = $this->assignmentsQuery($student, $period)->whereNull('faculty_id')->with('subject:id,code')->get()
            ->pluck('subject.code')->unique();
        if ($noInstructor->isNotEmpty()) {
            $warnings[] = 'No instructor assigned yet, so these can\'t be evaluated: '.$noInstructor->implode(', ').'.';
        }

        return $warnings;
    }

    // pending subjects for the reminder email
    public function pendingFacultyLabels(Student $student, ?ReviewPeriod $period): array
    {
        $submitted = $this->submittedIds($student, $period, Evaluation::TYPE_FACULTY, 'class_subject_id');

        return $this->assignmentsQuery($student, $period)
            ->whereNotNull('faculty_id')
            ->whereNotIn('class_subjects.id', $submitted)
            ->with(['subject:id,code', 'faculty:id,name'])
            ->get()
            ->map(fn ($cs) => "{$cs->subject->code} - Prof. {$cs->faculty->name}")
            ->all();
    }

    // flush right away, a submission changes completion status
    public function forget(): void
    {
        Cache::flush();
    }

    private function submittedIds(Student $student, ?ReviewPeriod $period, string $type, string $column): Collection
    {
        if (! $period) {
            return collect();
        }

        return Evaluation::where('student_id', $student->id)
            ->where('review_period_id', $period->id)
            ->where('evaluatee_type', $type)
            ->whereNotNull('submitted_at')
            ->pluck($column);
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
