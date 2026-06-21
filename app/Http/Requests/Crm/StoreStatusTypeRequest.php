<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreStatusTypeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
