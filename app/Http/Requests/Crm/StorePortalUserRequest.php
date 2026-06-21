<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePortalUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('portal_users')->where('tenant_id', tenant()->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
