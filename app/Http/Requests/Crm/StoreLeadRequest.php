<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
            'person_id' => ['nullable', Rule::exists('crm_people', 'id')->where('tenant_id', tenant()->id)],
            'organization_id' => ['nullable', Rule::exists('crm_organizations', 'id')->where('tenant_id', tenant()->id)],
            'source_id' => ['nullable', Rule::exists('crm_sources', 'id')->where('tenant_id', tenant()->id)],
            'status_id' => ['nullable', Rule::exists('crm_statuses', 'id')->where('tenant_id', tenant()->id)],
            'pipeline_id' => ['nullable', Rule::exists('crm_pipelines', 'id')->where('tenant_id', tenant()->id)],
            'pipeline_stage_id' => ['nullable', Rule::exists('crm_pipeline_stages', 'id')->where('tenant_id', tenant()->id)],
            'custom_fields' => ['nullable', 'json'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [Rule::exists('crm_tags', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
