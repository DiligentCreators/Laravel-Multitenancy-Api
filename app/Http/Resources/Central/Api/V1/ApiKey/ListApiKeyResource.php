<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\ApiKey;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApiKey */
class ListApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->permissions,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
