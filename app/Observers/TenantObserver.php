<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant;

class TenantObserver
{
    public function creating(Tenant $tenant): void {}

    public function created(Tenant $tenant): void {}

    public function updating(Tenant $tenant): void {}

    public function updated(Tenant $tenant): void {}

    public function saving(Tenant $tenant): void {}

    public function saved(Tenant $tenant): void {}

    public function deleting(Tenant $tenant): void {}

    public function deleted(Tenant $tenant): void
    {
        $tenant->domains()->delete();
        $tenant->users()->delete();
    }

    public function restoring(Tenant $tenant): void {}

    public function restored(Tenant $tenant): void
    {
        $tenant->domains()->onlyTrashed()->restore();
        $tenant->users()->onlyTrashed()->restore();
    }

    public function forceDeleting(Tenant $tenant): void {}

    public function forceDeleted(Tenant $tenant): void
    {
        $tenant->domains()->onlyTrashed()->forceDelete();
        $tenant->users()->onlyTrashed()->forceDelete();
    }
}
