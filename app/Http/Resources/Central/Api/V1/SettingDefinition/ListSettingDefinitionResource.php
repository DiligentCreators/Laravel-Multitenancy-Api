<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\SettingDefinition;

use App\Models\SettingDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SettingDefinition */
class ListSettingDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'default_value' => $this->default_value,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
