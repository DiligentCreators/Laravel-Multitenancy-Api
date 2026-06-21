<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
