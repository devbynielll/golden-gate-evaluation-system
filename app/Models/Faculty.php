<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasFactory;

    // table is "faculty", not the guessed "faculties"
    protected $table = 'faculty';

    protected $fillable = ['program_id', 'faculty_id', 'name', 'email', 'photo_path'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // relative path, not Storage::url() — that one's pinned to APP_URL
    public function photoUrl(): ?string
    {
        return $this->photo_path ? '/storage/'.$this->photo_path : null;
    }

    public function qualifiedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'faculty_subject');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }
}
