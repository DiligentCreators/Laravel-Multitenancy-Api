<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\WorkflowLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[UsePolicy(WorkflowLogPolicy::class)]

class WorkflowLog extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_workflow_logs';

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'trigger_event',
        'triggerable_type',
        'triggerable_id',
        'status',
        'result',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'json',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }
}
