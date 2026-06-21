<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SettingGroup;

use App\Http\Requests\BaseFormRequest;

class UpdateSettingGroupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:settings_groups,slug,'.$this->route('settings_group')?->id],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
