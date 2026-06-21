<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ticket;

class TicketObserver
{
    public function creating(Ticket $ticket): void {}

    public function created(Ticket $ticket): void {}

    public function updating(Ticket $ticket): void {}

    public function updated(Ticket $ticket): void {}

    public function saving(Ticket $ticket): void {}

    public function saved(Ticket $ticket): void {}

    public function deleting(Ticket $ticket): void {}

    public function deleted(Ticket $ticket): void {}

    public function restored(Ticket $ticket): void {}

    public function forceDeleted(Ticket $ticket): void {}
}
