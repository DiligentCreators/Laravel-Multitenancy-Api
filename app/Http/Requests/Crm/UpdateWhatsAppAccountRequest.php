<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateWhatsAppAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'in:meta_cloud'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:500'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,disconnected'],
        ];
    }
}
