<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PortalUser */
class PortalUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'invited_at' => $this->invited_at,
            'registered_at' => $this->registered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'links' => PortalPersonLinkResource::collection($this->whenLoaded('personLinks')),
        ];
    }
}
