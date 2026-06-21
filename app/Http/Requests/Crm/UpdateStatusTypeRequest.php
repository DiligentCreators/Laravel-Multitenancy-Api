<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateStatusTypeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:100'],
            'key' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
