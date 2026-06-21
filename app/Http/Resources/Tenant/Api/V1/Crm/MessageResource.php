<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction?->value,
            'body' => $this->body,
            'status' => $this->status?->value,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
