<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OfficeStaff extends Model
{
    protected $table = 'office_staff';
    protected $fillable = ['name', 'position'];
    protected $hidden = ['identity_key'];
    protected $casts = ['office_id' => 'integer', 'is_active' => 'boolean'];

    public static function identityKey(string $name, string $position): string
    {
        return hash('sha256', mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)))."\0".mb_strtolower(trim(preg_replace('/\s+/u', ' ', $position))));
    }

    protected static function booted(): void
    {
        static::saving(function (self $staff) {
            if ($staff->exists && $staff->isDirty('office_id')) {
                throw ValidationException::withMessages(['office_id' => 'Staff office ownership cannot be changed. Archive this record and register a new office assignment instead.']);
            }
            $staff->identity_key = self::identityKey($staff->name, $staff->position);
        });
        static::deleting(fn () => throw ValidationException::withMessages(['staff' => 'Archive staff instead of deleting historical references.']));
    }

    public function evaluations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StaffEvaluation::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
