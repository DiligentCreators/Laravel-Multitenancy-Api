<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Invoice;

use App\Http\Requests\BaseFormRequest;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'status' => ['nullable', 'string', 'in:draft,pending,paid,overdue,cancelled'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
