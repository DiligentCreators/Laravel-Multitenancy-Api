<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Central\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfController extends Controller
{
    public function __construct(
        private readonly InvoicePdfService $pdfService,
    ) {}

    public function download(Invoice $invoice): JsonResponse|StreamedResponse
    {
        if (! $invoice->paid_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice is not paid yet.',
            ], 400);
        }

        return $this->pdfService->download($invoice);
    }

    public function stream(Invoice $invoice): JsonResponse|StreamedResponse
    {
        if (! $invoice->paid_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice is not paid yet.',
            ], 400);
        }

        return $this->pdfService->stream($invoice);
    }

    public function generate(Invoice $invoice): JsonResponse
    {
        $path = $this->pdfService->generate($invoice);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice PDF generated.',
            'data' => [
                'path' => $path,
            ],
        ]);
    }
}
