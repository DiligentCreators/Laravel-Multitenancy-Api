<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\PortalPersonLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PortalPersonLink */
class PortalPersonLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'portal_user_id' => $this->portal_user_id,
            'person_id' => $this->person_id,
            'organization_id' => $this->organization_id,
            'created_at' => $this->created_at,
            'portal_user' => new PortalUserResource($this->whenLoaded('portalUser')),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'first_name' => $this->person->first_name,
                'last_name' => $this->person->last_name,
                'email' => $this->person->email,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
        ];
    }
}
