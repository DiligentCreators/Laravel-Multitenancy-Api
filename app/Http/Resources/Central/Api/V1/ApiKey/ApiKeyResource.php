<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\ApiKey;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApiKey */
class ApiKeyResource extends JsonResource
{
    public bool $showKey = false;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->when($this->showKey, $this->key),
            'permissions' => $this->permissions,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
