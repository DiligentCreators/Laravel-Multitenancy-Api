<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\PaymentObserver;
use App\Policies\PaymentPolicy;
use Database\Factories\Central\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(PaymentFactory::class)]
#[ObservedBy(PaymentObserver::class)]
#[UsePolicy(PaymentPolicy::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'amount',
        'currency',
        'gateway',
        'transaction_id',
        'status',
        'paid_at',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
        ];
    }
}
