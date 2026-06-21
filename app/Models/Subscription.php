<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Observers\SubscriptionObserver;
use App\Policies\SubscriptionPolicy;
use Carbon\Carbon;
use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(SubscriptionFactory::class)]
#[ObservedBy(SubscriptionObserver::class)]
#[UsePolicy(SubscriptionPolicy::class)]
/**
 * @property-read SubscriptionBillingCycleEnum $billing_cycle
 * @property-read SubscriptionStatusEnum $status
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 * @property-read Carbon $starts_at
 * @property-read Carbon|null $ends_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'billing_cycle',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'billing_cycle' => SubscriptionBillingCycleEnum::class,
        'status' => SubscriptionStatusEnum::class,
    ];

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

    public function isActive(): bool
    {
        return $this->status->isActive() && ! $this->isExpired();
    }

    public function isTrial(): bool
    {
        return $this->status->isTrial() && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isSuspended(): bool
    {
        return $this->status->isSuspended();
    }

    public function isCurrentlyActive(): bool
    {
        return $this->isActive() || $this->isTrial();
    }

    public function daysRemaining(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return (int) max(0, Carbon::now()->startOfDay()->diffInDays($this->ends_at->startOfDay(), false));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatusEnum::ACTIVE,
            SubscriptionStatusEnum::TRIAL,
        ])->where(function (Builder $query): Builder {
            return $query->where('ends_at', '>=', Carbon::now())
                ->orWhereNull('ends_at');
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('ends_at', '<', Carbon::now());
    }

    public function scopeTrial(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatusEnum::TRIAL);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
        ];
    }
}
