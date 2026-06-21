<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\OrganizationPerson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationPerson */
class OrganizationPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'person_id' => $this->person_id,
            'role' => $this->role,
            'is_primary' => $this->is_primary,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'first_name' => $this->person->first_name,
                'last_name' => $this->person->last_name,
            ]),
        ];
    }
}
