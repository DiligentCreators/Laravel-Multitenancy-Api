<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\TimelineEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimelineEntry */
class TimelineEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'meta' => $this->meta,
            'occurred_at' => $this->occurred_at,
            'created_at' => $this->created_at,
            'causer' => $this->whenLoaded('causer', fn () => [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ]),
        ];
    }
}
