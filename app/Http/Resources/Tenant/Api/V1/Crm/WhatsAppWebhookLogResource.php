<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WhatsAppWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsAppWebhookLog */
class WhatsAppWebhookLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
