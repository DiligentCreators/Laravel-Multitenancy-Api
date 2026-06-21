<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type_id' => ['sometimes', 'exists:crm_status_types,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'key' => ['sometimes', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
