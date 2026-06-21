<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Person */
class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'custom_fields' => $this->custom_fields,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status' => StatusResource::make($this->whenLoaded('status')),
            'source' => SourceResource::make($this->whenLoaded('source')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'organizations' => $this->whenLoaded('organizations', fn () => $this->organizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'pivot' => [
                    'role' => $org->pivot->role,
                    'is_primary' => $org->pivot->is_primary,
                    'start_date' => $org->pivot->start_date,
                    'end_date' => $org->pivot->end_date,
                ],
            ])),
        ];
    }
}
