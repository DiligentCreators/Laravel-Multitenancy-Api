<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateMessageTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'in:whatsapp,sms,email,internal'],
            'category' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
