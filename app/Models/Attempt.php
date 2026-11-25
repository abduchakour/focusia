<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's single attempt at a quiz (rule 3, unique in DB).
 * started_at = server time when the quiz was downloaded;
 * answered_at = device time when the answers were given (may be offline);
 * synced_at = server time when the answers reached the server.
 */
class Attempt extends Model
{
    protected $fillable = [
        'quiz_id', 'student_id', 'status', 'started_at', 'answered_at',
        'synced_at', 'score', 'correct_count', 'total_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'synced_at' => 'datetime',
            'score' => 'decimal:2',
            'correct_count' => 'integer',
            'total_count' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
