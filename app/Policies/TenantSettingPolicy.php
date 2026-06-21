<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\TenantSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('tenant.list');
    }

    public function update(CentralUser $centralUser, ?TenantSetting $tenantSetting = null): bool
    {
        return $centralUser->can('tenant.update');
    }
}
