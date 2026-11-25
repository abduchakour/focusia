<?php

namespace App\Http\Controllers\Teacher\Concerns;

use App\Enums\Level;
use App\Http\Requests\Teacher\ContentRequest;
use App\Models\Group;
use App\Services\FileStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Create / update / delete logic shared by course materials and library resources. */
trait ManagesContent
{
    /**
     * Saves the fields, the file (new, kept or removed) and the target groups.
     *
     * @param  list<string>  $fields
     */
    protected function saveContent(ContentRequest $request, Model $content, array $fields, string $folder, FileStore $files): void
    {
        DB::transaction(function () use ($request, $content, $fields, $folder, $files) {
            $content->fill($request->safe()->only($fields));
            $content->url = $request->filled('url') ? $request->input('url') : null;

            $old = $content->getAttribute('file_path');
            if ($request->hasFile('file')) {
                $content->fill($files->store($request->file('file'), $folder));
            } elseif ($request->boolean('remove_file')) {
                $content->fill($files->forget(null));
            }
            $content->save();

            // The previous file is removed once the new state is saved.
            if ($old && $old !== $content->getAttribute('file_path')) {
                $files->disk()->delete($old);
            }

            // Rule 11: sync() never attaches the same group twice.
            $content->groups()->sync($request->validated('group_ids', []));
        });
    }

    /** @return array<string, mixed> */
    protected function formOptions(Request $request): array
    {
        return [
            'groups' => $request->user()->teachingGroups()->orderBy('name')->get(['id', 'name', 'subject', 'level'])
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name, 'subject' => $g->subject, 'level' => $g->level->value]),
            'levels' => collect(Level::cases())->map(fn (Level $l) => ['value' => $l->value, 'label' => $l->label()]),
            'limits' => [
                'max_mb' => round(config('focusia.max_upload_kb') / 1024),
                'extensions' => array_keys(config('focusia.file_types')),
            ],
        ];
    }
}
