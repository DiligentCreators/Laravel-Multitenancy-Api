<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Setting;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->is_encrypted ? '********' : $this->value,
            'type' => $this->type,
            'default_value' => $this->default_value,
            'validation_rules' => $this->validation_rules,
            'is_public' => $this->is_public,
            'is_encrypted' => $this->is_encrypted,
            'sort_order' => $this->sort_order,
            'group' => $this->whenLoaded('group'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
