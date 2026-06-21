<?php

namespace App\Models;

use Database\Factories\Central\TaxRegionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(TaxRegionFactory::class)]
class TaxRegion extends Model
{
    /** @use HasFactory<TaxRegionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<TaxRate, $this> */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }
}
