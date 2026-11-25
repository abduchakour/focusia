<?php

namespace App\Http\Presenters;

use App\Models\Group;
use Illuminate\Database\Eloquent\Model;

/** Shape of a material / library resource sent to the pages (never the storage path). */
final class ContentPresenter
{
    /**
     * Data of one material / resource for the pages (never the storage path).
     *
     * @return array<string, mixed>
     */
    public static function present(Model $content, string $kind): array
    {
        return [
            'id' => $content->getKey(),
            'title' => $content->getAttribute('title'),
            'description' => $content->getAttribute('description'),
            'type' => $content->getAttribute('type')->value,
            'type_label' => $content->getAttribute('type')->label(),
            'subject' => $content->getAttribute('subject'),
            'level' => $content->getAttribute('level')?->value,
            'url' => $content->getAttribute('url'),
            'file' => $content->getAttribute('file_name') ? [
                'name' => $content->getAttribute('file_name'),
                'size' => $content->getAttribute('file_size'),
                'extension' => $content->fileExtension(),
                'download' => route('files.link', ['kind' => $kind, 'id' => $content->getKey()]),
            ] : null,
            'groups' => $content->relationLoaded('groups')
                ? $content->getRelation('groups')->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values()
                : [],
            'created_at' => $content->getAttribute('created_at')?->toIso8601String(),
        ];
    }
}
