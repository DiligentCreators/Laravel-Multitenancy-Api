<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class MergeTagsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'source_id' => ['required', 'exists:crm_tags,id'],
            'target_id' => ['required', 'exists:crm_tags,id', 'different:source_id'],
        ];
    }
}
