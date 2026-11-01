<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'code', 'description', 'year_level', 'credits'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function qualifiedFaculty(): BelongsToMany
    {
        return $this->belongsToMany(Faculty::class, 'faculty_subject');
    }

    // actual offerings of this subject (a class section + instructor)
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }
}
