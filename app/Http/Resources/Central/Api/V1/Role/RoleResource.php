<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Role;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @var null|Collection<int, Permission> */
    protected ?Collection $allPermissions = null;

    /** @param null|Collection<int, Permission> $allPermissions */
    public function __construct($resource, ?Collection $allPermissions = null)
    {
        parent::__construct($resource);

        $this->allPermissions = $allPermissions;
    }

    public function toArray(Request $request): array
    {
        $permissions = $this->allPermissions?->map(fn (Permission $permission) => [
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'is_assigned' => $permission->is_assigned,
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions' => $permissions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
