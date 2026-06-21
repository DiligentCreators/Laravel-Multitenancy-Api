<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineStage */
class PipelineStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'pipeline' => PipelineResource::make($this->whenLoaded('pipeline')),
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'probability' => $this->probability,
            'is_won_stage' => $this->is_won_stage,
            'is_lost_stage' => $this->is_lost_stage,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
