<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class ListTenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('users') ? $this->users->first() : null;
        $domain = $this->relationLoaded('domains') ? $this->domains->first() : null;

        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'name' => $user?->name,
            'username' => $user?->username,
            'email' => $user?->email,
            'domain' => $domain?->domain,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
