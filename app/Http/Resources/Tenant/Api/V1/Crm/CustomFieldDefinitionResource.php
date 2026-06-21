<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomFieldDefinition */
class CustomFieldDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'name' => $this->name,
            'key' => $this->key,
            'type' => $this->type,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'is_unique' => $this->is_unique,
            'default_value' => $this->default_value,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
