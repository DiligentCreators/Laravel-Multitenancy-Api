<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        /** @var MorphableEntityResolver $resolver */
        $resolver = app(MorphableEntityResolver::class);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['nullable', Rule::exists('crm_statuses', 'id')->where('tenant_id', tenant()->id)],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
            'taskable_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($resolver::ALLOWED_TYPES))],
            'taskable_id' => ['required_with:taskable_type', 'integer', 'min:1'],
        ];
    }
}
