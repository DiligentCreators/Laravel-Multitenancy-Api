<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\Plan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AttachPlanFeatureRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'feature_id' => ['required', 'integer', Rule::exists('features', 'id')],
            'value' => ['required', 'string'],
        ];
    }
}
