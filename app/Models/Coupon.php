<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CouponObserver;
use App\Policies\CouponPolicy;
use Database\Factories\Central\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(CouponFactory::class)]
#[ObservedBy(CouponObserver::class)]
#[UsePolicy(CouponPolicy::class)]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'code',
        'type',
        'amount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function apply(float $amount): float
    {
        $couponAmount = (float) $this->amount;

        if ($this->type === 'percentage') {
            return $amount * ($couponAmount / 100);
        }

        return min($couponAmount, $amount);
    }

    public function markUsed(): void
    {
        $this->increment('used_count');
    }

    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
        ];
    }
}
