<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\User;

use App\Http\Requests\BaseFormRequest;
use App\Rules\PasswordRule;

class StoreUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:central_users,email',
            'password' => [
                'required',
                'string',
                new PasswordRule,
            ],

            'role' => [
                'required',
                'array',
                'min:1',
            ],

            'role.*' => [
                'required',
                'string',
                'exists:roles,name',
            ],
        ];
    }
}
