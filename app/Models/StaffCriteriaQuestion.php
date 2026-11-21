<?php

namespace App\Models;

use App\Models\Concerns\ProtectsStaffCriteriaHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StaffCriteriaQuestion extends CriteriaQuestion
{
    use ProtectsStaffCriteriaHistory;
    protected $table = 'staff_criteria_questions';
    protected $casts = ['locked_at' => 'datetime'];
    protected function historyFields(): array { return ['text', 'order', 'criteria_category_id']; }
    protected function ownershipField(): string { return 'criteria_category_id'; }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffCriteriaCategory::class, 'criteria_category_id');
    }

    // Freeze within the same transaction as the staff answer snapshots.
    public function freezeForHistory(): void
    {
        DB::transaction(function () {
            ReviewPeriod::orderBy('id')->sharedLock()->get();
            $categoryId = self::whereKey($this->id)->value('criteria_category_id');
            $category = StaffCriteriaCategory::whereKey($categoryId)->lockForUpdate()->firstOrFail();
            $question = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (! $category->locked_at) { $category->locked_at = now(); $category->save(); }
            if (! $question->locked_at) { $question->locked_at = now(); $question->save(); }
            $this->refresh();
            DB::afterCommit(fn () => \Illuminate\Support\Facades\Cache::flush());
        }, 3);
    }
}
