<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Coupon;

class CouponObserver
{
    public function creating(Coupon $coupon): void {}

    public function created(Coupon $coupon): void {}

    public function updating(Coupon $coupon): void {}

    public function updated(Coupon $coupon): void {}

    public function saving(Coupon $coupon): void {}

    public function saved(Coupon $coupon): void {}

    public function deleting(Coupon $coupon): void {}

    public function deleted(Coupon $coupon): void {}

    public function restored(Coupon $coupon): void {}

    public function forceDeleted(Coupon $coupon): void {}
}
