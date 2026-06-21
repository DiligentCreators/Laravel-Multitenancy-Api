<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Ticket;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class ListTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'tenant_id' => $this->tenant_id,
            'subject' => $this->subject,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
