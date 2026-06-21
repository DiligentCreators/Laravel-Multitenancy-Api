<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,queued,sent,delivered,read,failed'],
            'delivered_at' => ['nullable', 'date'],
            'read_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'json'],
        ];
    }
}
