<?php

namespace App\Http\Requests\Teacher;

use App\Enums\MaterialType;
use Illuminate\Validation\Rule;

class MaterialRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'material';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->contentRules(), 'type' => ['required', Rule::enum(MaterialType::class)]];
    }
}
