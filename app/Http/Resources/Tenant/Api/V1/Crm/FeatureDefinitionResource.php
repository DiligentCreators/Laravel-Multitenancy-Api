<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\FeatureDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeatureDefinition */
class FeatureDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'default_value' => $this->default_value,
            'is_usage_limit' => $this->is_usage_limit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
