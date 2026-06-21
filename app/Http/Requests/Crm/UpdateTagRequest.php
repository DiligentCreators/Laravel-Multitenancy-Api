<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
