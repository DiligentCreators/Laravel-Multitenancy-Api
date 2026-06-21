<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\EmailTemplate;

use App\Http\Requests\BaseFormRequest;

class UpdateEmailTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:email_templates,slug,'.$this->route('email_template')?->id],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
