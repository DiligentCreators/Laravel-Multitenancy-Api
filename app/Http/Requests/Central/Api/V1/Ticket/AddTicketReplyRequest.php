<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Ticket;

use App\Http\Requests\BaseFormRequest;

class AddTicketReplyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }
}
