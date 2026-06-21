<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\Coupon;
use Illuminate\Auth\Access\HandlesAuthorization;

class CouponPolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        if ($centralUser->can('coupons.list')) {
            return true;
        }

        return false;
    }

    public function view(CentralUser $centralUser, Coupon $coupon): bool
    {
        if ($centralUser->can('coupons.read')) {
            return true;
        }

        return false;
    }

    public function create(CentralUser $centralUser): bool
    {
        if ($centralUser->can('coupons.create')) {
            return true;
        }

        return false;
    }

    public function update(CentralUser $centralUser, Coupon $coupon): bool
    {
        if ($centralUser->can('coupons.update')) {
            return true;
        }

        return false;
    }

    public function delete(CentralUser $centralUser, Coupon $coupon): bool
    {
        if ($centralUser->can('coupons.delete')) {
            return true;
        }

        return false;
    }

    public function restore(CentralUser $centralUser, Coupon $coupon): bool
    {
        if ($centralUser->can('coupons.restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(CentralUser $centralUser, Coupon $coupon): bool
    {
        if ($centralUser->can('coupons.force.delete')) {
            return true;
        }

        return false;
    }
}
