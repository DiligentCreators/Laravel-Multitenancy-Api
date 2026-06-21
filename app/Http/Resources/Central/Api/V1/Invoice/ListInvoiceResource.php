<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Invoice;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class ListInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'tenant_id' => $this->tenant_id,
            'amount' => $this->amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
