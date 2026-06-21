<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.6; color: #333; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { color: #2563eb; margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .details { margin-bottom: 30px; }
        .details table { width: 100%; }
        .details td { padding: 5px 0; }
        .details td:last-child { text-align: right; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #2563eb; color: white; padding: 10px; text-align: left; }
        table.items td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 300px; margin-left: auto; }
        .totals td { padding: 5px 0; }
        .totals .grand-total { font-size: 16px; font-weight: bold; color: #2563eb; border-top: 2px solid #2563eb; padding-top: 10px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice</h1>
        <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
        <p><strong>Date:</strong> {{ $invoice->created_at->format('F j, Y') }}</p>
        <p><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A' }}</p>
    </div>

    <div class="details">
        <table>
            <tr>
                <td>
                    <strong>Bill To:</strong><br>
                    {{ $tenant->company_name ?? $tenant->name }}<br>
                    {{ $tenant->email }}
                </td>
                <td>
                    <strong>Subscription:</strong><br>
                    {{ $subscription?->plan?->name ?? 'N/A' }}<br>
                    {{ ucfirst($subscription?->billing_cycle?->value ?? 'monthly') }}
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $subscription?->plan?->name ?? 'Subscription' }} - {{ $invoice->created_at->format('F Y') }}</td>
                <td style="text-align: right;">${{ number_format($invoice->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td style="text-align: right;">${{ number_format($invoice->amount, 2) }}</td>
        </tr>
        @if ($invoice->discount_amount > 0)
        <tr>
            <td>Discount</td>
            <td style="text-align: right;">-${{ number_format($invoice->discount_amount, 2) }}</td>
        </tr>
        @endif
        @if ($invoice->tax_amount > 0)
        <tr>
            <td>Tax</td>
            <td style="text-align: right;">${{ number_format($invoice->tax_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td>Total</td>
            <td style="text-align: right;">${{ number_format($invoice->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>{{ config('app.name') }} &mdash; Thank you for your business!</p>
        <p>For questions about this invoice, please contact support.</p>
    </div>
</body>
</html>
