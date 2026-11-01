<?php

namespace App\Models;

use App\Support\EnrollmentOfferings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'program_id', 'class_section_id', 'is_irregular', 'student_number',
        'email', 'first_name', 'middle_name', 'last_name', 'suffix',
    ];

    protected $casts = [
        'is_irregular' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    // irregular students' subjects; pivot has the block they attend
    // one term at a time, active by default
    public function enrolledSubjects(?int $periodId = null): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject_enrollments')
            ->withPivot('class_section_id', 'review_period_id')
            ->withPivotValue('review_period_id', $periodId ?? ReviewPeriod::activeId())
            ->withTimestamps();
    }

    // what the student has to evaluate: their block, or the block they enrolled in per subject
    public function offerings(?int $periodId = null): Builder
    {
        $periodId ??= ReviewPeriod::activeId();

        // no term, nothing offered
        $query = ClassSubject::where('class_subjects.review_period_id', $periodId ?? 0);

        if (! $this->is_irregular) {
            return $query->where('class_subjects.class_section_id', $this->class_section_id);
        }

        return $query->whereExists(fn ($q) => $q
            ->selectRaw('1')
            ->from('student_subject_enrollments')
            ->where('student_subject_enrollments.student_id', $this->id)
            ->where(EnrollmentOfferings::matches()));
    }

    public function department(): ?Department
    {
        return $this->program?->department;
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ]));
    }
}
