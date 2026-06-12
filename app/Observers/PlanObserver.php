<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Plan;

class PlanObserver
{
    public function creating(Plan $plan): void {}

    public function created(Plan $plan): void {}

    public function updating(Plan $plan): void {}

    public function updated(Plan $plan): void {}

    public function saving(Plan $plan): void {}

    public function saved(Plan $plan): void {}

    public function deleting(Plan $plan): void {}

    public function deleted(Plan $plan): void
    {
        // Cascade soft delete to related models
        // $plan->related()->delete();
    }

    public function restored(Plan $plan): void
    {
        // Cascade restore to related models
        // $plan->related()->onlyTrashed()->restore();
    }

    public function forceDeleted(Plan $plan): void
    {
        // Cascade force delete to related models
        // $plan->related()->onlyTrashed()->forceDelete();
    }
}
