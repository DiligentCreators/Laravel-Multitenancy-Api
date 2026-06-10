<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1;

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
            'name' => $this->users()->first()->name,
            'username' => $this->users()->first()->username,
            'email' => $this->users()->first()->email,
            'domain' => $this->domains()->first()->domain,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
