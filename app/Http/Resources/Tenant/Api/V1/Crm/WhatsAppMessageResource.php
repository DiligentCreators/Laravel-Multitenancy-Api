<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsAppMessage */
class WhatsAppMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'person_id' => $this->person_id,
            'whatsapp_phone_number_id' => $this->whatsapp_phone_number_id,
            'provider_message_id' => $this->provider_message_id,
            'direction' => $this->direction?->value,
            'type' => $this->type?->value,
            'from_number' => $this->from_number,
            'to_number' => $this->to_number,
            'content' => $this->content,
            'media_url' => $this->media_url,
            'status' => $this->status?->value,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'failed_at' => $this->failed_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'conversation' => new ConversationResource($this->whenLoaded('conversation')),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'name' => $this->person->first_name.' '.$this->person->last_name,
            ]),
        ];
    }
}
