<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CriteriaCategory;
use App\Models\StaffCriteriaCategory;
use App\Models\StaffCriteriaQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Models\CriteriaQuestion;
use App\Models\EvaluationAnswer;
use App\Models\ReviewPeriod;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CriteriaController extends Controller
{
    private const CACHE_TTL = 60;

    private const PER_PAGE = 5;

    public function faculty(Request $request): Response
    {
        return $this->board($request, CriteriaCategory::TYPE_FACULTY, 'Admin/Criteria/Faculty', 'Faculty');
    }

    public function office(Request $request): Response
    {
        return $this->board($request, CriteriaCategory::TYPE_OFFICE, 'Admin/Criteria/Office', 'Office');
    }

    public function staff(Request $request): Response
    {
        return $this->board($request, 'staff', 'Admin/Criteria/Office', 'Office Staff');
    }

    private function categoryQuery(string $type)
    {
        return $type === 'staff' ? StaffCriteriaCategory::query() : CriteriaCategory::where('evaluatee_type', $type);
    }

    private function questionQuery(string $type)
    {
        return $type === 'staff' ? StaffCriteriaQuestion::query() : CriteriaQuestion::query();
    }

    private function board(Request $request, string $type, string $component, string $label): Response
    {
        $this->ensureValidType($type);
        $page = $request->validate(['page' => ['nullable', 'integer', 'min:1']])['page'] ?? 1;
        $officeId = $this->officeScope($request, $type);
        $ownOffice = $request->user()->isOfficeAdmin();

        $cacheKey = "admin-criteria:{$type}:".($officeId ?? 'shared').":{$ownOffice}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type, $page, $officeId, $ownOffice) {
            $scope = $this->categoryQuery($type)->where(function ($q) use ($officeId, $ownOffice) {
                $q->where('office_id', $officeId);
                if ($ownOffice) $q->orWhereNull('office_id');
            });
            $categories = (clone $scope)
                ->withCount('questions')
                ->with(['questions' => fn ($q) => $q->orderBy('order')])
                ->orderBy('section_number')
                ->orderBy('scope_key')
                ->paginate(self::PER_PAGE, page: $page)->withQueryString();

            return [
                'categories' => $categories,
                'totalQuestions' => $this->questionQuery($type)->whereHas(
                    'category',
                    fn ($q) => $q->whereIn('id', (clone $scope)->select('id')),
                )->count(),
            ];
        });

        // Compute role-specific locks outside the shared board cache.
        $permissions = [];
        if (in_array($type, ['faculty', 'office', 'staff'], true)) {
            $questions = $data['categories']->getCollection()->flatMap->questions;
            $usedIds = $type === 'staff'
                ? StaffCriteriaQuestion::whereIn('id', $questions->pluck('id'))->whereNotNull('locked_at')->pluck('id')
                : EvaluationAnswer::whereIn('criteria_question_id', $questions->pluck('id'))->distinct()->pluck('criteria_question_id');
            $usedCategoryIds = $questions->whereIn('id', $usedIds)->pluck('criteria_category_id');
            if ($type === 'staff') $usedCategoryIds = $usedCategoryIds->merge(StaffCriteriaCategory::whereIn('id', $data['categories']->getCollection()->pluck('id'))->whereNotNull('locked_at')->pluck('id'));
            $open = $this->submissionsOpen();
            $permissions = [
                'writesLocked' => $open,
                'readOnlyCategoryIds' => $ownOffice ? $data['categories']->getCollection()->whereNull('office_id')->pluck('id')->values() : [],
                'usedQuestionIds' => $usedIds->values(),
                'usedCategoryIds' => $usedCategoryIds->unique()->values(),
                'notice' => $type === 'staff'
                    ? ($open ? 'Staff Criteria are read-only while evaluation submissions are open.' : 'Staff criteria are separate from overall Office criteria. Shared questions apply institution-wide; office-owned questions apply only to their office. Historically locked questions and categories cannot be changed or deleted.')
                    : ($type === CriteriaCategory::TYPE_OFFICE
                    ? ($open ? 'Office Criteria are read-only while evaluation submissions are open.' : 'Shared questions apply to every office. Office-specific questions apply only to their office. Answered questions and categories cannot be edited or deleted.')
                    : ($open
                    ? 'Faculty Criteria are read-only while evaluation submissions are open. Questions and categories used in evaluations cannot be changed or deleted.'
                    : 'Questions and categories used in evaluations cannot be changed or deleted. New questions apply to future submissions, including when a period is reopened; existing answers are not changed.')),
            ];
        }

        return Inertia::render($component, [
            'label' => $label,
            'criteriaType' => $type,
            'categories' => $data['categories'],
            'totalQuestions' => $data['totalQuestions'],
            'criteriaPermissions' => $permissions,
            ...(in_array($type, ['office', 'staff'], true) ? [
                'selectedOfficeId' => $officeId,
                'criteriaOfficeName' => $officeId ? Office::findOrFail($officeId)->name : null,
                'criteriaOffices' => $request->user()->isSuperAdmin() ? Office::orderBy('name')->get(['id', 'code', 'name']) : [],
            ] : []),
        ]);
    }

    public function storeCategory(Request $request, string $type): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type) {
            $this->ensureValidType($type);

            $officeId = $this->officeScope($request, $type);
            $this->protectWrite($request, $type);
            $data = $this->validatedCategory($request, $type, officeId: $officeId);
            $this->categoryQuery($type)->create([...$data, ...($type !== 'staff' ? ['evaluatee_type' => $type] : []), 'office_id' => $officeId]);
            Cache::flush();

            return back()->with('status', 'Category added.');
        });
    }

    public function updateCategory(Request $request, string $type, CriteriaCategory $category): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type, $category) {
            $this->ensureValidType($type);
            $this->ensureCategoryBelongsToType($category, $type);

            $this->protectWrite($request, $type, category: $category);
            $category->update($this->validatedCategory($request, $type, $category));
            Cache::flush();

            return back()->with('status', 'Category updated.');
        });
    }

    public function destroyCategory(Request $request, string $type, CriteriaCategory $category): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type, $category) {
            $this->ensureValidType($type);
            $this->ensureCategoryBelongsToType($category, $type);

            $this->protectWrite($request, $type, category: $category);
            $category->delete();
            Cache::flush();

            return back()->with('status', 'Category deleted.');
        });
    }

    public function storeQuestion(Request $request, string $type, CriteriaCategory $category): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type, $category) {
            $this->ensureValidType($type);
            $this->ensureCategoryBelongsToType($category, $type);

            $this->officeScope($request, $type, $category);
            $this->protectWrite($request, $type);
            $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
            $order = $category->questions()->max('order') + 1;
            $category->questions()->create([...$data, 'order' => $order]);
            Cache::flush();

            return back()->with('status', 'Question added.');
        });
    }

    public function updateQuestion(Request $request, string $type, CriteriaCategory $category, CriteriaQuestion $question): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type, $category, $question) {
            $this->ensureValidType($type);
            $this->ensureCategoryBelongsToType($category, $type);
            abort_unless($question->criteria_category_id === $category->id, 404);

            $this->officeScope($request, $type, $category);
            $this->protectWrite($request, $type, question: $question);
            $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
            $question->update($data);
            Cache::flush();

            return back()->with('status', 'Question updated.');
        });
    }

    public function destroyQuestion(Request $request, string $type, CriteriaCategory $category, CriteriaQuestion $question): RedirectResponse
    {
        return $this->write($request, function () use ($request, $type, $category, $question) {
            $this->ensureValidType($type);
            $this->ensureCategoryBelongsToType($category, $type);
            abort_unless($question->criteria_category_id === $category->id, 404);

            $this->officeScope($request, $type, $category);
            $this->protectWrite($request, $type, question: $question);
            $question->delete();
            Cache::flush();

            return back()->with('status', 'Question deleted.');
        });
    }

    private function validatedCategory(Request $request, string $type, ?CriteriaCategory $category = null, ?int $officeId = null): array
    {
        $unique = Rule::unique($type === 'staff' ? 'staff_criteria_categories' : 'criteria_categories', 'section_number')
            ->where('office_id', $category?->office_id ?? $officeId)->ignore($category?->id);
        if ($type !== 'staff') $unique->where('evaluatee_type', $type);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section' => [
                'required', 'integer', 'min:1', 'max:65535',
                $unique,
            ],
        ], [], ['section' => 'section number']);

        return [
            'name' => $validated['name'],
            'section_number' => $validated['section'],
        ];
    }

    private function ensureValidType(string $type): void
    {
        abort_unless(in_array($type, [CriteriaCategory::TYPE_FACULTY, CriteriaCategory::TYPE_OFFICE, 'staff'], true), 404);
        abort_if(request()->user()->isDepartmentAdmin() && $type !== CriteriaCategory::TYPE_FACULTY, 403);
        abort_if(request()->user()->isOfficeAdmin() && ! in_array($type, ['office', 'staff'], true), 403);
        if (request()->user()->isOfficeAdmin()) request()->user()->assignedOfficeOrAbort();
    }

    private function submissionsOpen(): bool
    {
        return ReviewPeriod::where('is_active', true)->where('is_submission_open', true)->exists();
    }

    private function protectWrite(Request $request, string $type, ?CriteriaCategory $category = null, ?CriteriaQuestion $question = null): void
    {
        // Every authorized role shares the same open-period and answered-history guards.
        if ($category) $this->officeScope($request, $type, $category);
        if ($this->submissionsOpen()) {
            throw ValidationException::withMessages(['criteria' => 'Close evaluation submissions before changing '.ucfirst($type).' Criteria.']);
        }
        if ($type === 'staff') {
            $used = $question ? $question->fresh()->locked_at
                : ($category && ($category->fresh()->locked_at || $category->questions()->whereNotNull('locked_at')->exists()));
            if ($used) throw ValidationException::withMessages(['criteria' => 'Historically locked staff criteria cannot be changed or deleted. Add a new question or category instead.']);
            return;
        }
        $answers = EvaluationAnswer::query();
        if ($question) {
            $answers->where('criteria_question_id', $question->id);
        } elseif ($category) {
            $answers->whereIn('criteria_question_id', $category->questions()->select('id'));
        } else {
            return;
        }
        // Protect every answer reference, including historical periods and incomplete records.
        if ($answers->exists()) {
            throw ValidationException::withMessages(['criteria' => 'Questions and categories used in evaluations cannot be changed or deleted. Add a new question instead.']);
        }
    }

    private function ensureCategoryBelongsToType(CriteriaCategory $category, string $type): void
    {
        abort_unless($category->evaluatee_type === $type, 404);
    }

    private function officeScope(Request $request, string $type, ?CriteriaCategory $category = null): ?int
    {
        if (! in_array($type, ['office', 'staff'], true)) return null;
        $requested = $request->validate(['office_id' => ['nullable', 'integer', 'exists:offices,id']])['office_id'] ?? null;
        $requested = $requested === null ? null : (int) $requested;
        if ($request->user()->isOfficeAdmin()) {
            $own = $request->user()->assignedOfficeOrAbort()->id;
            abort_if($request->exists('office_id') && $requested !== $own, 403);
            abort_if($category && $category->office_id !== $own, 403);
            return $own;
        }
        // Ownership is immutable; an edit cannot move shared or private questions.
        if ($category) {
            abort_if($request->exists('office_id') && $requested !== $category->office_id, 403);
            return $category->office_id;
        }
        return $requested;
    }

    private function write(Request $request, \Closure $action): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($request, $action) {
                // Serialize questionnaire edits with period opening and student submissions.
                ReviewPeriod::orderBy('id')->lockForUpdate()->get();
                if ($request->user()->isOfficeAdmin()) User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $staffCategory = $request->route('staffCategory');
                if ($staffCategory instanceof StaffCriteriaCategory) StaffCriteriaCategory::whereKey($staffCategory->id)->lockForUpdate()->firstOrFail();
                $result = $action();
                DB::afterCommit(fn () => Cache::flush());
                return $result;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (! $request->routeIs('staff-criteria.*')) throw $error;
            throw ValidationException::withMessages(['criteria' => 'This staff criteria section or question order already exists. Reload and try again.']);
        }
    }
}
