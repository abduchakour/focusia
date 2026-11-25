<?php

namespace App\Http\Requests\Student;

use App\Models\Assignment;
use App\Rules\AcceptedFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/** Rule 14: a file, a text, or both (the previous file can be kept when replacing). */
class SubmitHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Assignment $assignment */
        $assignment = $this->route('assignment');

        return Gate::allows('view', $assignment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:'.config('focusia.max_upload_kb'), new AcceptedFile],
            'remove_file' => ['boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Assignment $assignment */
            $assignment = $this->route('assignment');
            $current = $assignment->submissions()->where('student_id', $this->user()->id)->value('file_path');
            $keepsFile = $current !== null && ! $this->boolean('remove_file');

            if (! $this->hasFile('file') && ! $keepsFile && ! $this->filled('body')) {
                $validator->errors()->add('body', 'Joignez un fichier, écrivez votre réponse, ou les deux.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $megabytes = round(config('focusia.max_upload_kb') / 1024);

        return [
            'file.uploaded' => "Le fichier n'a pas pu être envoyé (taille maximale : {$megabytes} Mo).",
            'file.max' => "Le fichier dépasse la taille maximale de {$megabytes} Mo.",
        ];
    }
}
