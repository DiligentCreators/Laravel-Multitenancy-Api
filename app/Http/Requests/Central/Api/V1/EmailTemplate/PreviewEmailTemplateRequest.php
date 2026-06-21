<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\EmailTemplate;

use App\Http\Requests\BaseFormRequest;

class PreviewEmailTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'variables' => ['nullable', 'array'],
        ];
    }
}
