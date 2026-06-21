<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    public function creating(Payment $payment): void {}

    public function created(Payment $payment): void {}

    public function updating(Payment $payment): void {}

    public function updated(Payment $payment): void {}

    public function saving(Payment $payment): void {}

    public function saved(Payment $payment): void {}

    public function deleting(Payment $payment): void {}

    public function deleted(Payment $payment): void {}

    public function restored(Payment $payment): void {}

    public function forceDeleted(Payment $payment): void {}
}
