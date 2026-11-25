<?php

namespace App\Http\Requests\Teacher;

use App\Rules\AcceptedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation of materials and library resources.
 * Rule 10: a file, a link, or both. Rule 11: never twice the same group.
 * Rule 20: accepted types checked on the real content (MIME) AND the extension, 10 MB max.
 */
abstract class ContentRequest extends FormRequest
{
    /** Route parameter name of the edited content ("material" or "resource"). */
    abstract protected function routeKey(): string;

    protected function content(): ?Model
    {
        $content = $this->route($this->routeKey());

        return $content instanceof Model ? $content : null;
    }

    public function authorize(): bool
    {
        // Ownership first: an intruder gets 403, never validation details (rule 9).
        $content = $this->content();

        return $content === null || Gate::allows('manage', $content);
    }

    /** @return array<string, mixed> */
    protected function contentRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:'.config('focusia.max_upload_kb'), new AcceptedFile],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'remove_file' => ['boolean'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'distinct', Rule::exists('groups', 'id')->where('teacher_id', $this->user()->id)],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $keepsFile = $this->content()?->getAttribute('file_path') !== null && ! $this->boolean('remove_file');

            if (! $this->hasFile('file') && ! $keepsFile && ! $this->filled('url')) {
                $validator->errors()->add('file', 'Ajoutez un fichier, un lien, ou les deux.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $megabytes = round(config('focusia.max_upload_kb') / 1024);

        return [
            'title.required' => 'Donnez un titre.',
            'file.uploaded' => "Le fichier n'a pas pu être envoyé (taille maximale : {$megabytes} Mo).",
            'file.max' => "Le fichier dépasse la taille maximale de {$megabytes} Mo.",
            'url.url' => 'Le lien doit être une adresse web complète, commençant par http:// ou https://.',
            'group_ids.*.distinct' => 'Un groupe est sélectionné deux fois.',
            'group_ids.*.exists' => "Ce groupe n'existe pas ou ne vous appartient pas.",
        ];
    }
}
