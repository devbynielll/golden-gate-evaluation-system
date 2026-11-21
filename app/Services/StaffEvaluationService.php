<?php

namespace App\Services;

use App\Models\{OfficeStaff, ReviewPeriod, StaffCriteriaCategory, StaffEvaluation, Student};

class StaffEvaluationService
{
    public function criteria(int $officeId): array
    {
        return StaffCriteriaCategory::forQuestionnaire('staff', $officeId)
            ->with(['questions' => fn ($q) => $q->orderBy('order')->orderBy('id')])
            ->orderBy('section_number')->orderBy('scope_key')->orderBy('id')->get()
            ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name,
                'section' => (string) $category->section_number,
                'questions' => $category->questions->map(fn ($question) => ['id' => $question->id, 'text' => $question->text])->values(),
            ])->all();
    }

    public function board(Student $student, ?ReviewPeriod $period, int $page = 1): array
    {
        $departmentId = $student->department()?->id;
        $query = OfficeStaff::where('is_active', true)->whereHas('office', fn ($q) => $q
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $departmentId)));
        $submitted = $period ? StaffEvaluation::where('student_id', $student->id)->where('review_period_id', $period->id)
            ->pluck('office_staff_id') : collect();
        $total = (clone $query)->count();
        $completed = (clone $query)->whereIn('id', $submitted)->count();
        $paginator = $query->with('office')->orderBy('office_id')->orderBy('name')->orderBy('id')->paginate(6, page: $page);
        $criteria = [];
        $rows = $paginator->getCollection()->map(function ($staff) use ($submitted, &$criteria) {
            $criteria[$staff->office_id] ??= $this->criteria($staff->office_id);
            return ['id' => $staff->id, 'name' => $staff->name, 'subtitle' => "{$staff->position} | {$staff->office->name}",
                'completed' => $submitted->contains($staff->id), 'categories' => $criteria[$staff->office_id]];
        });
        return ['officeStaff' => ['data' => $rows->values(), 'meta' => [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
        ]], 'staffProgress' => ['completed' => $completed, 'total' => $total]];
    }
}
