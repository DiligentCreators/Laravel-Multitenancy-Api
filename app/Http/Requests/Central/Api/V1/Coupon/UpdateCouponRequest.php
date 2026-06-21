<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Coupon;

use App\Http\Requests\BaseFormRequest;

class UpdateCouponRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:255', 'unique:coupons,code,'.$this->route('coupon')],
            'type' => ['nullable', 'string', 'in:percentage,fixed'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
