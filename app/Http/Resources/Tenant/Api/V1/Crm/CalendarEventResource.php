<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CalendarEvent */
class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'all_day' => $this->all_day,
            'status' => $this->status,
            'location' => $this->location,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'eventable_type' => $this->eventable_type,
            'eventable_id' => $this->eventable_id,
            'recurring_event_pattern_id' => $this->recurring_event_pattern_id,
        ];
    }
}
