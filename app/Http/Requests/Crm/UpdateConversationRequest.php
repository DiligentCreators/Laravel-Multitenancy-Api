<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:open,closed,archived'],
            'metadata' => ['nullable', 'json'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
        ];
    }
}
