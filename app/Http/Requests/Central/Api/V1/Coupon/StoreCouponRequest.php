<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Coupon;

use App\Http\Requests\BaseFormRequest;

class StoreCouponRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'amount' => ['required', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
