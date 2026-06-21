<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\OverageCharge;

use App\Enums\Central\OverageChargeStatusEnum;
use App\Http\Requests\BaseFormRequest;

class UpdateOverageChargeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', OverageChargeStatusEnum::values())],
        ];
    }
}
