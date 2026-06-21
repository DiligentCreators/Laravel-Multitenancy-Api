<?php

namespace App\Models;

use Database\Factories\Central\ProrationRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ProrationRecordFactory::class)]
class ProrationRecord extends Model
{
    /** @use HasFactory<ProrationRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'type',
        'credit_amount',
        'charge_amount',
        'net_amount',
        'currency',
        'details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credit_amount' => 'decimal:2',
            'charge_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'details' => 'array',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
