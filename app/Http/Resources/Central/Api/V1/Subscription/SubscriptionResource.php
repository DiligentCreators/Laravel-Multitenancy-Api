<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Subscription;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 *
 * @property-read SubscriptionBillingCycleEnum $billing_cycle
 * @property-read SubscriptionStatusEnum $status
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => $this->when($this->relationLoaded('tenant'), function () {
                return [
                    'id' => $this->tenant_id,
                    'name' => $this->tenant->name,
                    'email' => $this->tenant->email,
                ];
            }),
            'plan' => $this->when($this->relationLoaded('plan'), function () {
                return [
                    'id' => $this->plan_id,
                    'name' => $this->plan->name,
                    'monthly_price' => $this->plan->monthly_price,
                    'yearly_price' => $this->plan->yearly_price,
                    'trial_days' => $this->plan->trial_days,
                    'is_active' => $this->plan->is_active,
                ];
            }),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'billing_cycle' => $this->billing_cycle->label(),
            'status' => $this->status->label(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
