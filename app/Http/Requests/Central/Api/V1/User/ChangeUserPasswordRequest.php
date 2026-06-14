<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\User;

use App\Http\Requests\BaseFormRequest;

class ChangeUserPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
