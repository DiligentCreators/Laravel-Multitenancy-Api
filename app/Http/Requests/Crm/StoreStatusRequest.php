<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type_id' => ['required', 'exists:crm_status_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
