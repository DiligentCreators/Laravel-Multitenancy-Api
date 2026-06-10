<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1;

use App\Http\Requests\BaseFormRequest;
use App\Rules\DomainRule;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],

            'domain' => [
                'required',
                new DomainRule,
                Rule::unique('domains', 'domain'),
            ],
        ];
    }
}
