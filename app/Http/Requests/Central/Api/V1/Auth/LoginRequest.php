<?php

namespace App\Http\Requests\Central\Api\V1\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:central_users,email'],
            'password' => ['required', 'string'],
        ];
    }
}
