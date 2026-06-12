<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Plan;

use App\Http\Requests\BaseFormRequest;
use App\Rules\SlugRule;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255',
                new SlugRule,
                Rule::unique('plans', 'slug')->ignore($this->route('plan')),
            ],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*' => ['required', 'string', 'max:255'],
        ];
    }
}
