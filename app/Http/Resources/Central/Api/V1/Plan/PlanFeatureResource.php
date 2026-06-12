<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Plan;

use App\Models\Feature;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Feature
 *
 * @property-read PlanFeature|null $pivot
 */
class PlanFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'value' => $this->pivot?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
