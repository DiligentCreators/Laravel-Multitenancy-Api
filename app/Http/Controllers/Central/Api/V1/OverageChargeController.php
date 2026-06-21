<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\OverageCharge\UpdateOverageChargeRequest;
use App\Http\Resources\Central\Api\V1\OverageCharge\ListOverageChargeResource;
use App\Http\Resources\Central\Api\V1\OverageCharge\OverageChargeResource;
use App\Models\OverageCharge;
use App\Services\ApiResponseService;
use App\Services\Central\OverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OverageChargeController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly OverageService $overageService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', OverageCharge::class);

        $charges = OverageCharge::query()
            ->with('tenant')
            ->when(request()->filled('tenant_id'), fn ($q) => $q->where('tenant_id', request('tenant_id')))
            ->when(request()->filled('status'), fn ($q) => $q->where('status', request('status')))
            ->orderBy(request('sort', 'created_at'), request('direction', 'desc'))
            ->paginate($this->perPage(request()))
            ->withQueryString();

        return $this->api->success(
            'Overage charges retrieved successfully',
            ListOverageChargeResource::collection($charges),
        );
    }

    public function show(OverageCharge $overageCharge): JsonResponse
    {
        Gate::authorize('view', $overageCharge);

        $overageCharge->load('tenant');

        return $this->api->success(
            'Overage charge retrieved successfully',
            new OverageChargeResource($overageCharge),
        );
    }

    public function update(UpdateOverageChargeRequest $request, OverageCharge $overageCharge): JsonResponse
    {
        Gate::authorize('update', $overageCharge);

        $status = $request->input('status');

        $result = match ($status) {
            'invoiced' => $this->overageService->markInvoiced($overageCharge),
            'paid' => $this->overageService->markPaid($overageCharge),
            'waived' => $this->overageService->markWaived($overageCharge),
            default => $overageCharge,
        };

        return $this->api->success(
            'Overage charge updated successfully',
            new OverageChargeResource($result),
        );
    }
}
