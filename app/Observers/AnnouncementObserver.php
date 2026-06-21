<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Announcement;

class AnnouncementObserver
{
    public function creating(Announcement $announcement): void {}

    public function created(Announcement $announcement): void {}

    public function updating(Announcement $announcement): void {}

    public function updated(Announcement $announcement): void {}

    public function saving(Announcement $announcement): void {}

    public function saved(Announcement $announcement): void {}

    public function deleting(Announcement $announcement): void {}

    public function deleted(Announcement $announcement): void {}

    public function restored(Announcement $announcement): void {}

    public function forceDeleted(Announcement $announcement): void {}
}
