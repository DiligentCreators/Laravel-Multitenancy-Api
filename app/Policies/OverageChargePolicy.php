<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\OverageCharge;
use Illuminate\Auth\Access\HandlesAuthorization;

class OverageChargePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('overage-charges.list');
    }

    public function view(CentralUser $centralUser, OverageCharge $overageCharge): bool
    {
        return $centralUser->can('overage-charges.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('overage-charges.create');
    }

    public function update(CentralUser $centralUser, OverageCharge $overageCharge): bool
    {
        return $centralUser->can('overage-charges.update');
    }

    public function delete(CentralUser $centralUser, OverageCharge $overageCharge): bool
    {
        return $centralUser->can('overage-charges.delete');
    }
}
