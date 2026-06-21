<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crm\Activity;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'activityable_type' => ['sometimes'] + $resolver->getValidationRule(),
            'activityable_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::in(Activity::TYPES)],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
