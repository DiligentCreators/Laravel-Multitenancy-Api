<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1;

use App\Http\Requests\BaseFormRequest;
use App\Rules\DomainRule;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $user = $tenant->users()->first();
        $domain = $tenant->domains()->first();

        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'string', 'email', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8'],

            'domain' => [
                'required',
                new DomainRule,
                Rule::unique('domains', 'domain')->ignore($domain),
            ],
        ];
    }
}
