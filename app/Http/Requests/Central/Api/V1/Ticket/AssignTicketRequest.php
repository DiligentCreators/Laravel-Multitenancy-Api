<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Ticket;

use App\Http\Requests\BaseFormRequest;

class AssignTicketRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'exists:central_users,id'],
        ];
    }
}
