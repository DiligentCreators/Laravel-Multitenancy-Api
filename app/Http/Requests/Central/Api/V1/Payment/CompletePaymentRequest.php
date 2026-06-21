<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Payment;

use App\Http\Requests\BaseFormRequest;

class CompletePaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:255'],
        ];
    }
}
