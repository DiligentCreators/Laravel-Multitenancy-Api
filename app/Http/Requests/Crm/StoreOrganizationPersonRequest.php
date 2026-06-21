<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationPersonRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'organization_id' => ['required', Rule::exists('crm_organizations', 'id')->where('tenant_id', tenant()->id)],
            'person_id' => ['required', Rule::exists('crm_people', 'id')->where('tenant_id', tenant()->id)],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
