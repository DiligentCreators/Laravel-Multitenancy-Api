<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'status' => StatusResource::make($this->whenLoaded('status')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'taskable_type' => $this->taskable_type,
            'taskable_id' => $this->taskable_id,
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}
