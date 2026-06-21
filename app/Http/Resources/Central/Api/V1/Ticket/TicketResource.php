<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Api\V1\Ticket;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'tenant_id' => $this->tenant_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_user' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ]),
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $reply->user ? [
                    'id' => $reply->user->id,
                    'name' => $reply->user->name,
                ] : null,
                'created_at' => $reply->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
