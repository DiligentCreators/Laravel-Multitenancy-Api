<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $resolver = app(MorphableEntityResolver::class);

        return [
            'commentable_type' => $resolver->getValidationRule(),
            'commentable_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', Rule::exists('crm_comments', 'id')->where('tenant_id', tenant()->id)],
            'content' => ['required', 'string'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
