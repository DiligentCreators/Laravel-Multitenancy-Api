<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Coupon;

use App\Http\Requests\BaseFormRequest;

class ApplyCouponRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
