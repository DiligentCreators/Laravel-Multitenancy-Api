<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\SettingGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('settings-groups.list');
    }

    public function view(CentralUser $centralUser, SettingGroup $settingGroup): bool
    {
        return $centralUser->can('settings-groups.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('settings-groups.create');
    }

    public function update(CentralUser $centralUser, SettingGroup $settingGroup): bool
    {
        return $centralUser->can('settings-groups.update');
    }

    public function delete(CentralUser $centralUser, SettingGroup $settingGroup): bool
    {
        return $centralUser->can('settings-groups.delete');
    }
}
