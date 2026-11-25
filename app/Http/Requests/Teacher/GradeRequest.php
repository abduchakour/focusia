<?php

namespace App\Http\Requests\Teacher;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Rule 16: grade from 0 to 20, decimals allowed (comma or dot), optional comment. */
class GradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        $submission = $this->route('submission');

        return $assignment instanceof Assignment && $submission instanceof Submission
            && $submission->assignment_id === $assignment->id
            && Gate::allows('manage', $assignment);
    }

    protected function prepareForValidation(): void
    {
        // Phones often type "12,5".
        if (is_string($this->input('grade'))) {
            $this->merge(['grade' => str_replace(',', '.', trim($this->input('grade')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade' => ['required', 'numeric', 'decimal:0,2', 'between:0,20'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'grade.required' => 'Saisissez une note.',
            'grade.numeric' => 'La note doit être un nombre, par exemple 14 ou 12,5.',
            'grade.decimal' => 'La note a au plus deux décimales.',
            'grade.between' => 'La note est comprise entre 0 et 20.',
        ];
    }
}
