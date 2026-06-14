<?php

namespace App\Http\Controllers\Tenant\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Tenant\Api\V1\Auth\LoginResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->api->error(
                message: 'The provided credentials are incorrect.',
            );
        }

        if ($user->trashed()) {
            return $this->api->error(
                message: 'The provided credentials are incorrect.',
            );
        }

        if ($user->is_suspended) {
            return $this->api->error(
                message: 'The provided credentials are incorrect.',
            );
        }

        return $this->api->success(
            message: 'Login successful',
            data: new LoginResource($user)
        );
    }
}
