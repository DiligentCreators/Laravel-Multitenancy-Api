<?php

namespace App\Models\Crm;

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureOverride extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_tenant_feature_overrides';

    protected $fillable = [
        'tenant_id',
        'feature_id',
        'value',
        'reason',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(FeatureDefinition::class, 'feature_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }
}
