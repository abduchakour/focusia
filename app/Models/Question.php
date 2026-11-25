<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A single-answer question with exactly 4 choices, worth 1 point. */
class Question extends Model
{
    protected $fillable = ['quiz_id', 'notion_id', 'text', 'position'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function notion(): BelongsTo
    {
        return $this->belongsTo(Notion::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(Choice::class)->orderBy('position');
    }
}
