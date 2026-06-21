<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\SettingGroup;

use App\Models\SettingGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SettingGroup */
class SettingGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'settings' => $this->whenLoaded('settings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
