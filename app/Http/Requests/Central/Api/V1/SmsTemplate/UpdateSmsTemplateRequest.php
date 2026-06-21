<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\SmsTemplate;

use App\Http\Requests\BaseFormRequest;

class UpdateSmsTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:sms_templates,slug,'.$this->route('sms_template')?->id],
            'message' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
