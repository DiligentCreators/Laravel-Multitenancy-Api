<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('tickets.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Ticket $ticket): bool
    {
        if ($centralUser->can('tickets.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('tickets.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Ticket $ticket): bool
    {
        if ($centralUser->can('tickets.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Ticket $ticket): bool
    {
        if ($centralUser->can('tickets.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Ticket $ticket): bool
    {
        if ($centralUser->can('tickets.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Ticket $ticket): bool
    {
        if ($centralUser->can('tickets.force.delete')) {
            return true;
        }

        return false;
    }
}
