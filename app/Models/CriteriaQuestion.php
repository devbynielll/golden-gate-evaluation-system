<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriteriaQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['criteria_category_id', 'text', 'order'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CriteriaCategory::class, 'criteria_category_id');
    }
}
