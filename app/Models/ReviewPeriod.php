<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year', 'semester', 'start_date', 'end_date',
        'is_active', 'is_submission_open',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'is_submission_open' => 'boolean',
        ];
    }

    // the term students are evaluating
    public static function activeId(): ?int
    {
        return static::where('is_active', true)->value('id');
    }

    public function label(): string
    {
        return "{$this->academic_year} {$this->semester}";
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
