<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\StatusTypePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(StatusTypePolicy::class)]
class StatusType extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_status_types';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'name',
        'key',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class, 'type_id');
    }
}
