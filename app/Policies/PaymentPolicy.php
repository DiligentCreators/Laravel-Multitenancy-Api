<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('payments.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Payment $payment): bool
    {
        if ($centralUser->can('payments.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('payments.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Payment $payment): bool
    {
        if ($centralUser->can('payments.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Payment $payment): bool
    {
        if ($centralUser->can('payments.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Payment $payment): bool
    {
        if ($centralUser->can('payments.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Payment $payment): bool
    {
        if ($centralUser->can('payments.force.delete')) {
            return true;
        }

        return false;
    }
}
