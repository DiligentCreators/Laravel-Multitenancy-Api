<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Payment;

use App\Http\Requests\BaseFormRequest;

class StorePaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'gateway' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:pending,completed,failed,refunded'],
        ];
    }
}
