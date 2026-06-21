<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Ticket;

use App\Http\Requests\BaseFormRequest;

class UpdateTicketRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:central_users,id'],
        ];
    }
}
