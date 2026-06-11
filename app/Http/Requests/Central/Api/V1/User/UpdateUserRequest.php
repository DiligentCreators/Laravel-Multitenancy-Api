<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api\V1\User;

use App\Http\Requests\BaseFormRequest;
use App\Rules\PasswordRule;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('central_users', 'email')->ignore($this->route('user')),
            ],
            'password' => [
                'sometimes',
                'string',
                new PasswordRule,
            ],
            'role' => [
                'sometimes',
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
