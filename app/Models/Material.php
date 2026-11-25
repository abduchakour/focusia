<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Course material: a file, a link, or both (rule 10). */
class Material extends Model
{
    protected $fillable = [
        'teacher_id', 'title', 'description', 'type',
        'file_path', 'file_name', 'mime_type', 'file_size', 'url',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['type' => MaterialType::class, 'file_size' => 'integer'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
