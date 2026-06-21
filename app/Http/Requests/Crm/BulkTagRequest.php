<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class BulkTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:person,organization,lead,task,document'],
            'entity_ids' => ['required', 'array', 'min:1'],
            'entity_ids.*' => ['required', 'integer'],
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*' => ['required', 'exists:crm_tags,id'],
        ];
    }
}
