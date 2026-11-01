<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

// which offering an irregular enrollment points to: its block, or the only offering of the subject. same term only
class EnrollmentOfferings
{
    private const SINGLE_OFFERING = '(select count(*) from class_subjects cs_all where cs_all.subject_id = class_subjects.subject_id and cs_all.review_period_id = class_subjects.review_period_id) = 1';

    public static function matches(string $enrollment = 'student_subject_enrollments'): Closure
    {
        return fn ($join) => $join
            ->whereColumn("{$enrollment}.subject_id", 'class_subjects.subject_id')
            // same term
            ->whereColumn("{$enrollment}.review_period_id", 'class_subjects.review_period_id')
            ->where(fn ($w) => $w
                ->whereColumn("{$enrollment}.class_section_id", 'class_subjects.class_section_id')
                ->orWhere(fn ($n) => $n->whereNull("{$enrollment}.class_section_id")->whereRaw(self::SINGLE_OFFERING)));
    }

    // joins class_subjects to an enrollments query
    public static function join(Builder $query): Builder
    {
        return $query->join('class_subjects', function (JoinClause $join) {
            $join->where(self::matches());
        });
    }
}
