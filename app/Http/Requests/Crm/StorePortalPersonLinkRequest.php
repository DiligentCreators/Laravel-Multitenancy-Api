<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePortalPersonLinkRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'portal_user_id' => ['required', 'integer', Rule::exists('portal_users', 'id')->where('tenant_id', tenant()->id)],
            'person_id' => ['nullable', 'integer', Rule::exists('crm_people', 'id')->where('tenant_id', tenant()->id)],
            'organization_id' => ['nullable', 'integer', Rule::exists('crm_organizations', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
