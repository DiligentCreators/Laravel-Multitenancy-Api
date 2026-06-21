<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class AccessDocumentShareRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string'],
        ];
    }
}
