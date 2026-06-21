<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateOrganizationPersonRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'exists:crm_organizations,id'],
            'person_id' => ['sometimes', 'exists:crm_people,id'],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
