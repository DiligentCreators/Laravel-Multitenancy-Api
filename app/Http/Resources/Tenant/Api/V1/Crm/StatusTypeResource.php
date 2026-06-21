<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\StatusType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StatusType */
class StatusTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'name' => $this->name,
            'key' => $this->key,
            'statuses' => StatusResource::collection($this->whenLoaded('statuses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
