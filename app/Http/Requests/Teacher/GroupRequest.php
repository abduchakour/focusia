<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a group. */
class GroupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'level' => ['required', Rule::enum(Level::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nom du groupe',
            'subject' => 'matière',
            'level' => 'niveau',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'subject' => is_string($this->subject) ? trim($this->subject) : $this->subject,
        ]);
    }
}
