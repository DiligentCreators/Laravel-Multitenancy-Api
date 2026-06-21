<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\User;

class StoreMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', 'in:inbound,outbound'],
            'body' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,queued,sent,delivered,read,failed'],
            'sender_type' => ['required', 'string', 'in:user,person,organization'],
            'sender_id' => ['required', 'integer', 'min:1', $this->senderExists()],
            'sent_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'json'],
        ];
    }

    protected function senderExists(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $typeMap = [
                'user' => User::class,
                'person' => Person::class,
                'organization' => Organization::class,
            ];

            $fqcn = $typeMap[$this->input('sender_type')] ?? null;

            if ($fqcn !== null && ! $fqcn::where('id', $value)->exists()) {
                $fail('The selected sender does not exist.');
            }
        };
    }
}
