<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class UpdateNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'noteable_type' => ['sometimes'] + $resolver->getValidationRule(),
            'noteable_id' => ['sometimes', 'integer'],
            'content' => ['sometimes', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
