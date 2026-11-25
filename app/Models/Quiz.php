<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $due_at
 * @property Carbon $corrections_at
 * @property Carbon|null $published_at
 */
class Quiz extends Model
{
    protected $fillable = [
        'teacher_id', 'title', 'description', 'subject', 'level',
        'duration_minutes', 'due_at', 'corrections_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'duration_minutes' => 'integer',
            'due_at' => 'datetime',
            'corrections_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Rule 7: correct answers are never exposed before this moment. */
    public function correctionsAreVisible(): bool
    {
        return $this->corrections_at->lte(now());
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('created_at');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
