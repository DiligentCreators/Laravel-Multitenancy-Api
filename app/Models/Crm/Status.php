<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\StatusPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(StatusPolicy::class)]
class Status extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_statuses';

    protected $fillable = [
        'tenant_id',
        'type_id',
        'name',
        'key',
        'color',
        'order',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StatusType::class, 'type_id');
    }
}
