<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'value' => $this->value,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->format('Y-m-d'),
            'won_at' => $this->won_at,
            'lost_at' => $this->lost_at,
            'custom_fields' => $this->custom_fields,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'status' => StatusResource::make($this->whenLoaded('status')),
            'source' => SourceResource::make($this->whenLoaded('source')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'first_name' => $this->person->first_name,
                'last_name' => $this->person->last_name,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'pipeline' => PipelineResource::make($this->whenLoaded('pipeline')),
            'pipeline_stage' => PipelineStageResource::make($this->whenLoaded('pipelineStage')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
