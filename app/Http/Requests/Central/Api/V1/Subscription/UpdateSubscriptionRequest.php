<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Subscription;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Http\Requests\BaseFormRequest;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSubscriptionRequest extends BaseFormRequest
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

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Subscription $subscription */
                $subscription = $this->route('subscription');

                if (! $subscription) {
                    return;
                }

                $currentStatus = $subscription->status;
                $newStatus = SubscriptionStatusEnum::tryFrom($this->input('status'));

                if ($newStatus === null) {
                    return;
                }

                $allowedTransitions = [
                    SubscriptionStatusEnum::TRIAL->value => [
                        SubscriptionStatusEnum::ACTIVE->value,
                        SubscriptionStatusEnum::EXPIRED->value,
                        SubscriptionStatusEnum::CANCELLED->value,
                    ],
                    SubscriptionStatusEnum::ACTIVE->value => [
                        SubscriptionStatusEnum::EXPIRED->value,
                        SubscriptionStatusEnum::CANCELLED->value,
                        SubscriptionStatusEnum::SUSPENDED->value,
                    ],
                    SubscriptionStatusEnum::EXPIRED->value => [
                        SubscriptionStatusEnum::ACTIVE->value,
                        SubscriptionStatusEnum::CANCELLED->value,
                    ],
                    SubscriptionStatusEnum::CANCELLED->value => [
                        SubscriptionStatusEnum::ACTIVE->value,
                    ],
                    SubscriptionStatusEnum::SUSPENDED->value => [
                        SubscriptionStatusEnum::ACTIVE->value,
                        SubscriptionStatusEnum::EXPIRED->value,
                    ],
                ];

                $currentValue = $currentStatus instanceof SubscriptionStatusEnum
                    ? $currentStatus->value
                    : $currentStatus;

                $allowed = $allowedTransitions[$currentValue] ?? [];

                if (! in_array($newStatus->value, $allowed, true) && $currentValue !== $newStatus->value) {
                    $validator->errors()->add(
                        'status',
                        "Invalid status transition from '{$currentValue}' to '{$newStatus->value}'."
                    );
                }
            },
        ];
    }
}
