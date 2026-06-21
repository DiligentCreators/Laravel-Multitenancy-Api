<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantObserver
{
    public function restoring(Tenant $tenant): void {}

    public function restored(Tenant $tenant): void
    {
        $tenant->users()->onlyTrashed()->restore();
        $tenant->domains()->onlyTrashed()->restore();
        $tenant->subscriptions()->onlyTrashed()->restore();
    }

    public function forceDeleted(Tenant $tenant): void
    {
        DB::table('model_has_roles')
            ->whereIn('model_id', $tenant->users()->withTrashed()->pluck('id'))
            ->where('model_type', User::class)
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('model_id', $tenant->users()->withTrashed()->pluck('id'))
            ->where('model_type', User::class)
            ->delete();

        $tenant->subscriptions()->onlyTrashed()->forceDelete();
        $tenant->domains()->onlyTrashed()->forceDelete();
        $tenant->users()->onlyTrashed()->forceDelete();
    }
}
