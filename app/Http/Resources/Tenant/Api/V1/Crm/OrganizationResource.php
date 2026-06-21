<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'custom_fields' => $this->custom_fields,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status' => StatusResource::make($this->whenLoaded('status')),
            'source' => SourceResource::make($this->whenLoaded('source')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'people' => $this->whenLoaded('people', fn () => $this->people->map(fn ($person) => [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'pivot' => [
                    'role' => $person->pivot->role,
                    'is_primary' => $person->pivot->is_primary,
                    'start_date' => $person->pivot->start_date,
                    'end_date' => $person->pivot->end_date,
                ],
            ])),
        ];
    }
}
