<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Invoice;

use App\Http\Requests\BaseFormRequest;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'status' => ['nullable', 'string', 'in:draft,pending,paid,overdue,cancelled'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
