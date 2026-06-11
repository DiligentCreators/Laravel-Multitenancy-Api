<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Role;

class RoleObserver
{
    public function creating(Role $role): void {}

    public function created(Role $role): void {}

    public function updating(Role $role): void {}

    public function updated(Role $role): void {}

    public function saving(Role $role): void {}

    public function saved(Role $role): void {}
}
