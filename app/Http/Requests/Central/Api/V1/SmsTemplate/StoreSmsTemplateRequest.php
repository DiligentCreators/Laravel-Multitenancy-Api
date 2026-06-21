<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SmsTemplate;

use App\Http\Requests\BaseFormRequest;

class StoreSmsTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:sms_templates,slug'],
            'message' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
