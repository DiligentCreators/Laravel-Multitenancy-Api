<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateWorkflowRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'trigger_event' => ['sometimes', 'string', 'max:100'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'actions.*.type' => ['required_with:actions', 'string', 'in:assign_owner,update_field,create_task,send_notification'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
