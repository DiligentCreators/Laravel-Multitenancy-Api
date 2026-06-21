<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreSourceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', Rule::unique('crm_sources')->where('tenant_id', tenant()->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
