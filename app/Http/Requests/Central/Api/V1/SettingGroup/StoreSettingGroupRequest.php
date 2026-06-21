<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SettingGroup;

use App\Http\Requests\BaseFormRequest;

class StoreSettingGroupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:settings_groups,slug'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
