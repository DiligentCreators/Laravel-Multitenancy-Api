<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SettingDefinition;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingDefinitionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255',
                Rule::unique('setting_definitions', 'key')->ignore($this->route('setting_definition')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'default_value' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
