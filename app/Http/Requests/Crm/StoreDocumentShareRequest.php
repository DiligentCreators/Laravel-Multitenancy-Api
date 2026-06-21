<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreDocumentShareRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'share_token' => ['nullable', 'string', 'max:255', 'unique:crm_document_shares,share_token'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ];
    }
}
