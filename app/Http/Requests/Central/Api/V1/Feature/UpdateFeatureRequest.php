<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Feature;

use App\Http\Requests\BaseFormRequest;
use App\Rules\SlugRule;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                new SlugRule,
                Rule::unique('features', 'slug')->ignore($this->route('feature')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['boolean', 'integer', 'decimal', 'string'])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
