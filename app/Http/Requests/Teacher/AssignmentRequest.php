<?php

namespace App\Http\Requests\Teacher;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Homework: due date required (rule 13), material optional, several groups never twice (rule 11). */
class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return ! $assignment instanceof Assignment || Gate::allows('manage', $assignment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teacher = $this->user()->id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['required', 'string', 'max:10000'],
            'due_at' => ['required', 'date'],
            'allow_late' => ['boolean'],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->where('teacher_id', $teacher)],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'distinct', Rule::exists('groups', 'id')->where('teacher_id', $teacher)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre au devoir.',
            'instructions.required' => 'Écrivez la consigne.',
            'due_at.required' => 'Choisissez la date limite.',
            'material_id.exists' => "Ce support n'existe pas ou ne vous appartient pas.",
            'group_ids.required' => 'Choisissez au moins un groupe.',
            'group_ids.min' => 'Choisissez au moins un groupe.',
            'group_ids.*.distinct' => 'Un groupe est sélectionné deux fois.',
            'group_ids.*.exists' => "Ce groupe n'existe pas ou ne vous appartient pas.",
        ];
    }
}
