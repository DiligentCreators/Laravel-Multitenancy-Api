<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Payment\CompletePaymentRequest;
use App\Http\Requests\Central\Api\V1\Payment\StorePaymentRequest;
use App\Http\Requests\Central\Api\V1\Payment\UpdatePaymentRequest;
use App\Http\Resources\Central\Api\V1\Payment\ListPaymentResource;
use App\Http\Resources\Central\Api\V1\Payment\PaymentResource;
use App\Models\Payment;
use App\Services\ApiResponseService;
use App\Services\Central\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PaymentService $paymentService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $payments = $this->paymentService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'payments retrieved successfully',
            ListPaymentResource::collection($payments),
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        Gate::authorize('create', Payment::class);

        $payment = $this->paymentService->create($request->validated());

        return $this->api->success(
            'Payment has been created successfully',
            new PaymentResource($payment),
            201,
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        if ($payment->trashed()) {
            return $this->api->notFound('Payment has been deleted.');
        }

        return $this->api->success(
            'Payment retrieved successfully',
            new PaymentResource($payment),
        );
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        Gate::authorize('update', $payment);

        if ($payment->trashed()) {
            return $this->api->notFound('Cannot update a deleted payment.');
        }

        $this->paymentService->update($payment, $request->validated());

        return $this->api->success(
            'Payment has been updated successfully',
            new PaymentResource($payment),
        );
    }

    public function destroy(Payment $payment): JsonResponse
    {
        Gate::authorize('delete', $payment);

        if ($payment->trashed()) {
            return $this->api->notFound('Payment is already deleted.');
        }

        $payment->delete();

        return $this->api->success(
            'Payment has been deleted successfully',
            null,
            200,
        );
    }

    public function markCompleted(CompletePaymentRequest $request, Payment $payment): JsonResponse
    {
        Gate::authorize('update', $payment);

        $this->paymentService->markCompleted($payment, $request->input('transaction_id'));

        return $this->api->success(
            'Payment has been completed successfully',
            new PaymentResource($payment->fresh()),
        );
    }

    public function markFailed(Payment $payment): JsonResponse
    {
        Gate::authorize('update', $payment);

        $this->paymentService->markFailed($payment);

        return $this->api->success(
            'Payment has been marked as failed',
            new PaymentResource($payment->fresh()),
        );
    }

    public function markRefunded(Payment $payment): JsonResponse
    {
        Gate::authorize('update', $payment);

        $this->paymentService->markRefunded($payment);

        return $this->api->success(
            'Payment has been refunded',
            new PaymentResource($payment->fresh()),
        );
    }

    public function restore(Payment $payment): JsonResponse
    {
        Gate::authorize('restore', $payment);

        if (! $payment->trashed()) {
            return $this->api->notFound('Payment is not deleted.');
        }

        $payment->restore();

        return $this->api->success(
            'Payment has been restored successfully',
            new PaymentResource($payment),
        );
    }

    public function forceDelete(Payment $payment): JsonResponse
    {
        Gate::authorize('forceDelete', $payment);

        if (! $payment->trashed()) {
            return $this->api->error('Payment must be deleted before force deleting.', 400);
        }

        $payment->forceDelete();

        return $this->api->success(
            'Payment has been force deleted successfully',
            null,
            200,
        );
    }
}
