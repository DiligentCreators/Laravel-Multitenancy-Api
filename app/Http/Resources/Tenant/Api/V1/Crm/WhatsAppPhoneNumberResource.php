<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WhatsAppPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsAppPhoneNumber */
class WhatsAppPhoneNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'phone_number_id' => $this->phone_number_id,
            'display_phone_number' => $this->display_phone_number,
            'verified_name' => $this->verified_name,
            'quality_rating' => $this->quality_rating,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
