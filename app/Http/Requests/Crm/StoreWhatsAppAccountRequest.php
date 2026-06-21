<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreWhatsAppAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'in:meta_cloud'],
            'business_account_id' => ['required', 'string', 'max:255'],
            'app_id' => ['required', 'string', 'max:255'],
            'app_secret' => ['required', 'string', 'max:500'],
            'access_token' => ['required', 'string', 'max:2000'],
            'webhook_verify_token' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,disconnected'],
        ];
    }
}
