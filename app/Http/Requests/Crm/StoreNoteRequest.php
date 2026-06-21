<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'noteable_type' => $resolver->getValidationRule(),
            'noteable_id' => ['required', 'integer'],
            'content' => ['required', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
