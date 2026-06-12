<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Feature;

class FeatureObserver
{
    public function creating(Feature $feature): void {}

    public function created(Feature $feature): void {}

    public function updating(Feature $feature): void {}

    public function updated(Feature $feature): void {}

    public function saving(Feature $feature): void {}

    public function saved(Feature $feature): void {}

    public function deleting(Feature $feature): void {}

    public function deleted(Feature $feature): void
    {
        // Cascade soft delete to related models
        // $feature->related()->delete();
    }

    public function restored(Feature $feature): void
    {
        // Cascade restore to related models
        // $feature->related()->onlyTrashed()->restore();
    }

    public function forceDeleted(Feature $feature): void
    {
        // Cascade force delete to related models
        // $feature->related()->onlyTrashed()->forceDelete();
    }
}
