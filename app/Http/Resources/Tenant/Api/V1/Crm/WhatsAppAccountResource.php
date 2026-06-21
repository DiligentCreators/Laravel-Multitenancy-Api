<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsAppAccount */
class WhatsAppAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider?->value,
            'business_account_id' => $this->business_account_id,
            'app_id' => $this->app_id,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'phone_numbers' => WhatsAppPhoneNumberResource::collection($this->whenLoaded('phoneNumbers')),
            'phone_numbers_count' => $this->whenCounted('phoneNumbers'),
        ];
    }
}
