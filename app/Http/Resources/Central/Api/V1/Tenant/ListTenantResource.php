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
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'name' => $this->users()->withTrashed()->first()?->name,
            'username' => $this->users()->withTrashed()->first()?->username,
            'email' => $this->users()->withTrashed()->first()?->email,
            'domain' => $this->domains()->withTrashed()->first()?->domain,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
