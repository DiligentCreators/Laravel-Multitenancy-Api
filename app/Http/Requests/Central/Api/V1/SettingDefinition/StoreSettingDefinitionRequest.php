<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SettingDefinition;

use App\Http\Requests\BaseFormRequest;

class StoreSettingDefinitionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:setting_definitions,key'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'default_value' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
