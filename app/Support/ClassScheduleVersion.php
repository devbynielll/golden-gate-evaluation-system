<?php

namespace App\Support;

use App\Models\ClassSection;
use Illuminate\Support\Collection;

class ClassScheduleVersion
{
    public static function for(ClassSection $section, int $periodId, Collection $offerings): string
    {
        return hash('sha256', json_encode([
            $section->id, $section->program_id, $section->year_level, $section->block, $periodId,
            $offerings->sortBy('subject_id')->map(fn ($offering) => [
                $offering->id, $offering->subject_id, $offering->faculty_id,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
