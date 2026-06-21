<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WorkflowLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowLog */
class WorkflowLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'trigger_event' => $this->trigger_event,
            'triggerable_type' => $this->triggerable_type,
            'triggerable_id' => $this->triggerable_id,
            'status' => $this->status,
            'result' => $this->result,
            'error' => $this->error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
