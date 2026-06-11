<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CentralUser;

class CentralUserObserver
{
    public function creating(CentralUser $user): void {}

    public function created(CentralUser $user): void {}

    public function updating(CentralUser $user): void {}

    public function updated(CentralUser $user): void {}

    public function saving(CentralUser $user): void {}

    public function saved(CentralUser $user): void {}

    public function deleting(CentralUser $user): void {}

    public function deleted(CentralUser $user): void
    {
        // Cascade soft delete to related models
        // $user->related()->delete();
    }

    public function restored(CentralUser $user): void
    {
        // Cascade restore to related models
        // $user->related()->onlyTrashed()->restore();
    }

    public function forceDeleted(CentralUser $user): void
    {
        // Cascade force delete to related models
        // $user->related()->onlyTrashed()->forceDelete();
    }
}
