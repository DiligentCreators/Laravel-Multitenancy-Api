<?php

namespace App\Models;

use Database\Factories\Central\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(TaxRateFactory::class)]
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tax_region_id',
        'name',
        'rate',
        'type',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @return BelongsTo<TaxRegion, $this> */
    public function taxRegion(): BelongsTo
    {
        return $this->belongsTo(TaxRegion::class);
    }
}
