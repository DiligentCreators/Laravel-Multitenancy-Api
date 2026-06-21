<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Module;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModulePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('modules.list');
    }

    public function view(CentralUser $centralUser, Module $module): bool
    {
        return $centralUser->can('modules.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('modules.create');
    }

    public function update(CentralUser $centralUser, Module $module): bool
    {
        return $centralUser->can('modules.update');
    }
}
