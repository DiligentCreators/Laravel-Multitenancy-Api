<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    public function creating(Invoice $invoice): void {}

    public function created(Invoice $invoice): void {}

    public function updating(Invoice $invoice): void {}

    public function updated(Invoice $invoice): void {}

    public function saving(Invoice $invoice): void {}

    public function saved(Invoice $invoice): void {}

    public function deleting(Invoice $invoice): void {}

    public function deleted(Invoice $invoice): void {}

    public function restored(Invoice $invoice): void {}

    public function forceDeleted(Invoice $invoice): void {}
}
