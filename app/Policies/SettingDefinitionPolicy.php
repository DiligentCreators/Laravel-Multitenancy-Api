<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\SettingDefinition;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingDefinitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('setting-definitions.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, SettingDefinition $settingDefinition): bool
    {
        if ($centralUser->can('setting-definitions.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('setting-definitions.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, SettingDefinition $settingDefinition): bool
    {
        if ($centralUser->can('setting-definitions.update')) {
            return true;
        }

        return false;
    }
}
