<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\InvoiceObserver;
use App\Policies\InvoicePolicy;
use Database\Factories\Central\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(InvoiceFactory::class)]
#[ObservedBy(InvoiceObserver::class)]
#[UsePolicy(InvoicePolicy::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'invoice_number',
        'tenant_id',
        'subscription_id',
        'amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'invoice_number' => $this->invoice_number,
        ];
    }
}
