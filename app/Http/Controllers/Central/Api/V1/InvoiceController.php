<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Central\Api\V1\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Central\Api\V1\Invoice\InvoiceResource;
use App\Http\Resources\Central\Api\V1\Invoice\ListInvoiceResource;
use App\Models\Invoice;
use App\Services\ApiResponseService;
use App\Services\Central\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly InvoiceService $invoiceService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'invoices retrieved successfully',
            ListInvoiceResource::collection($invoices),
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return $this->api->success(
            'Invoice has been created successfully',
            new InvoiceResource($invoice),
            201,
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        if ($invoice->trashed()) {
            return $this->api->notFound('Invoice has been deleted.');
        }

        return $this->api->success(
            'Invoice retrieved successfully',
            new InvoiceResource($invoice),
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->trashed()) {
            return $this->api->notFound('Cannot update a deleted invoice.');
        }

        $this->invoiceService->update($invoice, $request->validated());

        return $this->api->success(
            'Invoice has been updated successfully',
            new InvoiceResource($invoice),
        );
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);

        if ($invoice->trashed()) {
            return $this->api->notFound('Invoice is already deleted.');
        }

        $invoice->delete();

        return $this->api->success(
            'Invoice has been deleted successfully',
            null,
            200,
        );
    }

    public function markPaid(Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->trashed()) {
            return $this->api->notFound('Cannot update a deleted invoice.');
        }

        $this->invoiceService->markPaid($invoice);

        return $this->api->success(
            'Invoice has been marked as paid successfully',
            new InvoiceResource($invoice->fresh()),
        );
    }

    public function markOverdue(Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->trashed()) {
            return $this->api->notFound('Cannot update a deleted invoice.');
        }

        $this->invoiceService->markOverdue($invoice);

        return $this->api->success(
            'Invoice has been marked as overdue successfully',
            new InvoiceResource($invoice->fresh()),
        );
    }

    public function restore(Invoice $invoice): JsonResponse
    {
        Gate::authorize('restore', $invoice);

        if (! $invoice->trashed()) {
            return $this->api->notFound('Invoice is not deleted.');
        }

        $invoice->restore();

        return $this->api->success(
            'Invoice has been restored successfully',
            new InvoiceResource($invoice),
        );
    }

    public function forceDelete(Invoice $invoice): JsonResponse
    {
        Gate::authorize('forceDelete', $invoice);

        if (! $invoice->trashed()) {
            return $this->api->error('Invoice must be deleted before force deleting.', 400);
        }

        $invoice->forceDelete();

        return $this->api->success(
            'Invoice has been force deleted successfully',
            null,
            200,
        );
    }
}
