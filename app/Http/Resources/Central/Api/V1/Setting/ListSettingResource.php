<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Setting;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */
class ListSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'is_public' => $this->is_public,
            'is_encrypted' => $this->is_encrypted,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
