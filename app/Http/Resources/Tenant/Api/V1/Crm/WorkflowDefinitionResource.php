<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WorkflowDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowDefinition */
class WorkflowDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'entity_type' => $this->entity_type,
            'trigger_event' => $this->trigger_event,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
