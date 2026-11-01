<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriteriaCategory extends Model
{
    use HasFactory;

    public const TYPE_FACULTY = 'faculty';

    public const TYPE_OFFICE = 'office';

    protected $fillable = ['evaluatee_type', 'section_number', 'name', 'office_id'];

    protected $hidden = ['scope_key'];

    protected $casts = ['office_id' => 'integer'];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeForQuestionnaire(Builder $query, string $type, ?int $officeId = null): Builder
    {
        return $query->where('evaluatee_type', $type)->where(function (Builder $scope) use ($type, $officeId) {
            $scope->whereNull('office_id');
            if ($type === self::TYPE_OFFICE && $officeId !== null) {
                $scope->orWhere('office_id', $officeId);
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CriteriaQuestion::class);
    }
}
