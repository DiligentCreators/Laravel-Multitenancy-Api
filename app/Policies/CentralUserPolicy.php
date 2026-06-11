<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class CentralUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $actor): bool
    {
        if ($actor->can('users.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('users.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.force.delete')) {
            return true;
        }

        return false;
    }

    public function suspend(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.suspend')) {
            return true;
        }

        return false;
    }

    public function unsuspend(CentralUser $centralUser, CentralUser $target): bool
    {
        if ($centralUser->id === $target->id) {
            return false;
        }

        if ($this->isProtected($target)) {
            return false;
        }

        if ($centralUser->can('users.unsuspend')) {
            return true;
        }

        return false;
    }

    private function isProtected(CentralUser $user): bool
    {
        return in_array($user->id, config('central-protected-users.protected', []));
    }
}
