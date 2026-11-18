<?php

namespace App\Models;

use App\Models\Concerns\ProtectsStaffCriteriaHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffCriteriaCategory extends CriteriaCategory
{
    use ProtectsStaffCriteriaHistory;
    public const TYPE_STAFF = 'staff';
    protected $table = 'staff_criteria_categories';
    protected $fillable = ['section_number', 'name', 'office_id'];
    protected $casts = ['office_id' => 'integer', 'locked_at' => 'datetime'];

    public function getEvaluateeTypeAttribute(): string { return self::TYPE_STAFF; }
    protected function historyFields(): array { return ['name', 'section_number', 'office_id']; }
    protected function ownershipField(): string { return 'office_id'; }

    public function questions(): HasMany
    {
        return $this->hasMany(StaffCriteriaQuestion::class, 'criteria_category_id');
    }

    public function scopeForQuestionnaire(Builder $query, string $type, ?int $officeId = null): Builder
    {
        abort_unless($type === self::TYPE_STAFF, 404);
        return $query->where(fn ($q) => $q->whereNull('office_id')->when($officeId !== null, fn ($q) => $q->orWhere('office_id', $officeId)));
    }
}
