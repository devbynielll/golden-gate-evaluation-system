<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait ProtectsStaffCriteriaHistory
{
    protected static function bootProtectsStaffCriteriaHistory(): void
    {
        static::updating(function ($record) {
            $stored = static::findOrFail($record->id);
            $fields = $record->historyFields();
            if ($record->isDirty($record->ownershipField())) {
                throw ValidationException::withMessages(['criteria' => 'Criteria ownership and question categories cannot be changed.']);
            }
            $locked = $stored->locked_at || ($record instanceof \App\Models\StaffCriteriaCategory && $record->questions()->whereNotNull('locked_at')->exists());
            if ($locked && ($record->isDirty($fields) || ($record->isDirty('locked_at') && ($stored->locked_at || ! $record->locked_at)))) {
                throw ValidationException::withMessages(['criteria' => 'Historically locked staff criteria cannot be changed. Add a new question or category instead.']);
            }
        });
        static::deleting(function ($record) {
            if ($record->fresh()->locked_at || ($record instanceof \App\Models\StaffCriteriaCategory && $record->questions()->whereNotNull('locked_at')->exists())) {
                throw ValidationException::withMessages(['criteria' => 'Historically locked staff criteria cannot be deleted.']);
            }
        });
    }
}
