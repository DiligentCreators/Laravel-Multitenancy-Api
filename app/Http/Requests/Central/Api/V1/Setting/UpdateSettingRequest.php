<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Setting;

use App\Enums\Central\SettingTypeEnum;
use App\Http\Requests\BaseFormRequest;

class UpdateSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'group_id' => ['nullable', 'exists:settings_groups,id'],
            'key' => ['nullable', 'string', 'max:255', 'unique:system_settings,key,'.$this->route('system_setting')?->id],
            'label' => ['nullable', 'string', 'max:255'],
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
