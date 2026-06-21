<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'channel' => $this->channel?->value,
            'status' => $this->status?->value,
            'last_message_at' => $this->last_message_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'participants' => ConversationParticipantResource::collection($this->whenLoaded('participants')),
            'messages_count' => $this->whenCounted('messages'),
        ];
    }
}
