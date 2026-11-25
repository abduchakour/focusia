<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Choice extends Model
{
    public $timestamps = false;

    protected $fillable = ['question_id', 'text', 'is_correct', 'position'];

    /**
     * Never serialize the correct flag by accident (rule 7). Code that is allowed
     * to reveal it must call makeVisible('is_correct') explicitly.
     */
    protected $hidden = ['is_correct', 'correct_for_question'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
