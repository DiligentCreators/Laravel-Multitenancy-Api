<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'noteable_type' => $this->noteable_type,
            'noteable_id' => $this->noteable_id,
        ];
    }
}
