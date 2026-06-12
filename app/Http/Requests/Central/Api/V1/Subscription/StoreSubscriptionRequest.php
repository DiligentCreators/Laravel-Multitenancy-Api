<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Subscription;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string|exists:tenants,id',
            'plan_id' => 'required|integer|exists:plans,id',
            'starts_at' => 'required|date_format:Y-m-d',
            'billing_cycle' => [
                'required',
                'string',
                Rule::in(SubscriptionBillingCycleEnum::values()),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(SubscriptionStatusEnum::values()),
            ],
        ];
    }
}
