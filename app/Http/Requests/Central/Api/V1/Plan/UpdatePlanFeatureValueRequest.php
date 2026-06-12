<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Plan;

use App\Http\Requests\BaseFormRequest;

class UpdatePlanFeatureValueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
        ];
    }
}
