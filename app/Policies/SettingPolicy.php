<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Setting;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('settings.list');
    }

    public function view(CentralUser $centralUser, Setting $setting): bool
    {
        return $centralUser->can('settings.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('settings.create');
    }

    public function update(CentralUser $centralUser, Setting $setting): bool
    {
        return $centralUser->can('settings.update');
    }

    public function delete(CentralUser $centralUser, Setting $setting): bool
    {
        return $centralUser->can('settings.delete');
    }
}
