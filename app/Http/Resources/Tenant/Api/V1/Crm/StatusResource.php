<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Status */
class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_id' => $this->type_id,
            'type' => StatusTypeResource::make($this->whenLoaded('type')),
            'name' => $this->name,
            'key' => $this->key,
            'color' => $this->color,
            'order' => $this->order,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
