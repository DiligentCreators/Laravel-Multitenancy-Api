<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Setting;

use App\Enums\Central\SettingTypeEnum;
use App\Http\Requests\BaseFormRequest;

class StoreSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:settings_groups,id'],
            'key' => ['required', 'string', 'max:255', 'unique:system_settings,key'],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:'.implode(',', SettingTypeEnum::values())],
            'default_value' => ['nullable', 'string'],
            'validation_rules' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
