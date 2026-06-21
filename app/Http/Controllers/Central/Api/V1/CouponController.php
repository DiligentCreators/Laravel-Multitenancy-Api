<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Coupon\ApplyCouponRequest;
use App\Http\Requests\Central\Api\V1\Coupon\StoreCouponRequest;
use App\Http\Requests\Central\Api\V1\Coupon\UpdateCouponRequest;
use App\Http\Resources\Central\Api\V1\Coupon\CouponResource;
use App\Http\Resources\Central\Api\V1\Coupon\ListCouponResource;
use App\Models\Coupon;
use App\Services\ApiResponseService;
use App\Services\Central\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly CouponService $couponService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Coupon::class);

        $coupons = $this->couponService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'coupons retrieved successfully',
            ListCouponResource::collection($coupons),
        );
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        Gate::authorize('create', Coupon::class);

        $coupon = $this->couponService->create($request->validated());

        return $this->api->success(
            'Coupon has been created successfully',
            new CouponResource($coupon),
            201,
        );
    }

    public function show(Coupon $coupon): JsonResponse
    {
        Gate::authorize('view', $coupon);

        if ($coupon->trashed()) {
            return $this->api->notFound('Coupon has been deleted.');
        }

        return $this->api->success(
            'Coupon retrieved successfully',
            new CouponResource($coupon),
        );
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        Gate::authorize('update', $coupon);

        if ($coupon->trashed()) {
            return $this->api->notFound('Cannot update a deleted coupon.');
        }

        $this->couponService->update($coupon, $request->validated());

        return $this->api->success(
            'Coupon has been updated successfully',
            new CouponResource($coupon),
        );
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        Gate::authorize('delete', $coupon);

        if ($coupon->trashed()) {
            return $this->api->notFound('Coupon is already deleted.');
        }

        $coupon->delete();

        return $this->api->success(
            'Coupon has been deleted successfully',
            null,
            200,
        );
    }

    public function validateCoupon(ApplyCouponRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Coupon::class);

        $result = $this->couponService->validateCoupon($request->input('code'));

        return $this->api->success(
            $result['message'],
            $result,
        );
    }

    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Coupon::class);

        $result = $this->couponService->applyCoupon(
            $request->input('code'),
            (float) $request->input('amount'),
        );

        return $this->api->success(
            $result['valid'] ? 'Coupon applied successfully' : $result['message'],
            $result,
        );
    }

    public function restore(Coupon $coupon): JsonResponse
    {
        Gate::authorize('restore', $coupon);

        if (! $coupon->trashed()) {
            return $this->api->notFound('Coupon is not deleted.');
        }

        $coupon->restore();

        return $this->api->success(
            'Coupon has been restored successfully',
            new CouponResource($coupon),
        );
    }

    public function forceDelete(Coupon $coupon): JsonResponse
    {
        Gate::authorize('forceDelete', $coupon);

        if (! $coupon->trashed()) {
            return $this->api->error('Coupon must be deleted before force deleting.', 400);
        }

        $coupon->forceDelete();

        return $this->api->success(
            'Coupon has been force deleted successfully',
            null,
            200,
        );
    }
}
