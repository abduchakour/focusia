<?php

namespace App\Http\Requests\Teacher;

use App\Models\Notion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A notion name is unique per teacher and subject (also enforced by the DB). */
class NotionRequest extends FormRequest
{
    /** Ownership is checked before validation: an intruder gets 403, not validation errors. */
    public function authorize(): bool
    {
        $notion = $this->route('notion');

        return ! $notion instanceof Notion || $this->user()->can('manage', $notion);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Notion|null $notion */
        $notion = $this->route('notion');

        return [
            'subject' => ['required', 'string', 'max:150'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('notions')
                    ->where('teacher_id', $this->user()->id)
                    ->where('subject', $this->input('subject'))
                    ->ignore($notion?->id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Cette notion existe déjà pour cette matière.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nom de la notion', 'subject' => 'matière'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'subject' => is_string($this->subject) ? trim($this->subject) : $this->subject,
        ]);
    }
}
