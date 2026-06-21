<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class StoreWorkflowRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'entity_type' => ['required', 'string', 'max:100'],
            'trigger_event' => ['required', 'string', 'max:100'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', 'in:assign_owner,update_field,create_task,send_notification'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
