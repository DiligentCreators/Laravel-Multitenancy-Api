<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StorePersonRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'status_id' => ['nullable', 'exists:crm_statuses,id'],
            'source_id' => ['nullable', 'exists:crm_sources,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_fields' => ['nullable', 'json'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:crm_tags,id'],
        ];
    }
}
