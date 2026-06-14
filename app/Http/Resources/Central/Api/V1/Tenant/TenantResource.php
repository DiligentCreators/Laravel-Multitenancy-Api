<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Tenant;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->relationLoaded('users') ? $this->users->first() : null;
        /** @var Domain|null $domain */
        $domain = $this->relationLoaded('domains') ? $this->domains->first() : null;

        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'name' => $user?->name,
            'username' => $user?->username,
            'email' => $user?->email,
            'domain' => $domain?->domain,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
