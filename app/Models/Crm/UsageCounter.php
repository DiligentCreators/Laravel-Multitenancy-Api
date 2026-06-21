<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_usage_counters';

    protected $fillable = [
        'tenant_id',
        'feature_key',
        'count',
        'last_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'last_reset_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
