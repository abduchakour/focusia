<?php

namespace App\Models;

use App\Models\Concerns\PublishedInGroups;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Homework ("devoir").
 *
 * @property Carbon $due_at
 * @property bool $allow_late
 */
class Assignment extends Model
{
    use PublishedInGroups;

    protected $fillable = ['teacher_id', 'material_id', 'title', 'instructions', 'due_at', 'allow_late'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Optional linked material; set to null if the material is deleted (rule 19). */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('created_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
