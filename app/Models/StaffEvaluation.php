<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class StaffEvaluation extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['student_id', 'comments', 'submitted_at', 'created_at', 'updated_at'];
    protected $casts = ['submitted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Staff evaluation history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Staff evaluation history cannot be deleted.'));
    }

    public function answers(): HasMany { return $this->hasMany(StaffEvaluationAnswer::class); }
    public function staff(): BelongsTo { return $this->belongsTo(OfficeStaff::class, 'office_staff_id'); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function reviewPeriod(): BelongsTo { return $this->belongsTo(ReviewPeriod::class); }
}
