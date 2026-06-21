<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePipelineStageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'pipeline_id' => ['required', Rule::exists('crm_pipelines', 'id')->where('tenant_id', tenant()->id)],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_won_stage' => ['nullable', 'boolean'],
            'is_lost_stage' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
