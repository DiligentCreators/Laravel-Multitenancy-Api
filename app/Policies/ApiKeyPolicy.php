<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\CentralUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApiKeyPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('api-keys.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, ApiKey $apiKey): bool
    {
        if ($centralUser->can('api-keys.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('api-keys.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, ApiKey $apiKey): bool
    {
        if ($centralUser->can('api-keys.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, ApiKey $apiKey): bool
    {
        if ($centralUser->can('api-keys.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, ApiKey $apiKey): bool
    {
        if ($centralUser->can('api-keys.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, ApiKey $apiKey): bool
    {
        if ($centralUser->can('api-keys.force.delete')) {
            return true;
        }

        return false;
    }
}
