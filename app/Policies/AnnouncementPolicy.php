<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\CentralUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('announcements.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Announcement $announcement): bool
    {
        if ($centralUser->can('announcements.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('announcements.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Announcement $announcement): bool
    {
        if ($centralUser->can('announcements.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Announcement $announcement): bool
    {
        if ($centralUser->can('announcements.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Announcement $announcement): bool
    {
        if ($centralUser->can('announcements.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Announcement $announcement): bool
    {
        if ($centralUser->can('announcements.force.delete')) {
            return true;
        }

        return false;
    }
}
