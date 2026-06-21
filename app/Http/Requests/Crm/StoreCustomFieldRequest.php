<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreCustomFieldRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:person,organization,lead,task,document'],
            'name' => ['required', 'string', 'max:200'],
            'key' => ['nullable', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:text,textarea,number,decimal,date,datetime,checkbox,select,multiselect,email,phone,url,json'],
            'options' => ['nullable', 'array'],
            'is_required' => ['nullable', 'boolean'],
            'is_unique' => ['nullable', 'boolean'],
            'validation_rules' => ['nullable', 'array'],
            'default_value' => ['nullable'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
