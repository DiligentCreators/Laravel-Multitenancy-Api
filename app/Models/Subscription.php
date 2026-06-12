<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Observers\SubscriptionObserver;
use App\Policies\SubscriptionPolicy;
use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SubscriptionFactory::class)]
#[ObservedBy(SubscriptionObserver::class)]
#[UsePolicy(SubscriptionPolicy::class)]
/**
 * @property-read SubscriptionBillingCycleEnum $billing_cycle
 * @property-read SubscriptionStatusEnum $status
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'billing_cycle',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'billing_cycle' => SubscriptionBillingCycleEnum::class,
            'status' => SubscriptionStatusEnum::class,
        ];
    }

    /** @param Subscription $query */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
