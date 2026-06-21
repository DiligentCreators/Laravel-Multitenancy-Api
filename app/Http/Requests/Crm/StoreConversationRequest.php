<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:whatsapp,sms,email,internal'],
            'status' => ['nullable', 'string', 'in:open,closed,archived'],
            'metadata' => ['nullable', 'json'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', tenant()->id)],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.type' => ['required', 'string', 'in:person,organization,user'],
            'participants.*.id' => ['required', 'integer', 'min:1'],
            'participants.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
