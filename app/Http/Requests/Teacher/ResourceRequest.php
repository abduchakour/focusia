<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Level;
use App\Enums\ResourceType;
use Illuminate\Validation\Rule;

class ResourceRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'resource';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            'type' => ['required', Rule::enum(ResourceType::class)],
            'subject' => ['required', 'string', 'max:150'],
            'level' => ['required', Rule::enum(Level::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'subject.required' => 'Indiquez la matière.'];
    }
}
