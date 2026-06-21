<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\BaseFormRequest;

class UpdateCommentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'string'],
        ];
    }
}
