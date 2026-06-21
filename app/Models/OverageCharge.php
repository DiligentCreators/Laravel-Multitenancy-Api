<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Central\OverageChargeStatusEnum;
use App\Policies\OverageChargePolicy;
use Database\Factories\Central\OverageChargeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(OverageChargeFactory::class)]
#[UsePolicy(OverageChargePolicy::class)]
class OverageCharge extends Model
{
    /** @use HasFactory<OverageChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'quantity',
        'unit_price',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => OverageChargeStatusEnum::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
