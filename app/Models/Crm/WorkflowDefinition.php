<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\WorkflowDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(WorkflowDefinitionPolicy::class)]
class WorkflowDefinition extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_workflow_definitions';

    protected $fillable = [
        'tenant_id',
        'name',
        'entity_type',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'json',
            'actions' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
