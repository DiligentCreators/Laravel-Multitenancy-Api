<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crm\Activity;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'activityable_type' => $resolver->getValidationRule(),
            'activityable_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(Activity::TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
