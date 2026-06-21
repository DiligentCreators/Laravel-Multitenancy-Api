<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\EmailTemplate;

use App\Http\Requests\BaseFormRequest;

class StoreEmailTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:email_templates,slug'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
