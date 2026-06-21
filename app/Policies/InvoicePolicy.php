<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Invoice;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('invoices.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Invoice $invoice): bool
    {
        if ($centralUser->can('invoices.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('invoices.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Invoice $invoice): bool
    {
        if ($centralUser->can('invoices.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Invoice $invoice): bool
    {
        if ($centralUser->can('invoices.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Invoice $invoice): bool
    {
        if ($centralUser->can('invoices.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Invoice $invoice): bool
    {
        if ($centralUser->can('invoices.force.delete')) {
            return true;
        }

        return false;
    }
}
