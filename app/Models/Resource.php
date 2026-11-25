<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\ResourceType;
use App\Models\Concerns\PublishedInGroups;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Library resource (document, video, link, exercise): a file, a link, or both. */
class Resource extends Model
{
    use PublishedInGroups;

    protected $fillable = [
        'teacher_id', 'title', 'description', 'type', 'subject', 'level',
        'file_path', 'file_name', 'mime_type', 'file_size', 'url',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'level' => Level::class,
            'file_size' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('created_at');
    }
}
