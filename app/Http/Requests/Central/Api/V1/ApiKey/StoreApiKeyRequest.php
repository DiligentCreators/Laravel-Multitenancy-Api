<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\ApiKey;

use App\Http\Requests\BaseFormRequest;

class StoreApiKeyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
