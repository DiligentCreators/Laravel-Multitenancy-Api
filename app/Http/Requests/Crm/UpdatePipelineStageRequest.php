<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdatePipelineStageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_won_stage' => ['nullable', 'boolean'],
            'is_lost_stage' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
