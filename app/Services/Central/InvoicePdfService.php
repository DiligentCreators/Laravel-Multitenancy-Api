<?php

namespace App\Services\Central;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $tenant = $invoice->tenant;
        $subscription = $invoice->subscription;

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'subscription' => $subscription,
        ]);

        $filename = "invoice_{$invoice->invoice_number}.pdf";
        $path = "invoices/{$filename}";

        return DB::transaction(function () use ($pdf, $path, $invoice) {
            Storage::disk('local')->put($path, $pdf->output());

            $invoice->update(['invoice_pdf_url' => $path]);

            return $path;
        });
    }

    public function download(Invoice $invoice): StreamedResponse
    {
        $path = $invoice->invoice_pdf_url;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            $path = $this->generate($invoice);
        }

        return Storage::disk('local')->download($path, "invoice_{$invoice->invoice_number}.pdf");
    }

    public function stream(Invoice $invoice): StreamedResponse
    {
        $path = $invoice->invoice_pdf_url;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            $path = $this->generate($invoice);
        }

        return Storage::disk('local')->response($path);
    }
}
