<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffEvaluationAnswer extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['rating' => 'integer', 'is_office_specific' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Staff answer snapshots are immutable.'));
        static::deleting(fn () => throw new \LogicException('Staff answer snapshots cannot be deleted.'));
    }
}
