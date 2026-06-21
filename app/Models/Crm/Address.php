<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\AddressPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[UsePolicy(AddressPolicy::class)]
class Address extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_addresses';

    protected $fillable = [
        'tenant_id',
        'addressable_type',
        'addressable_id',
        'type',
        'country',
        'state',
        'city',
        'postal_code',
        'address_line_1',
        'address_line_2',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public const TYPES = ['billing', 'shipping', 'office', 'site', 'property'];
}
