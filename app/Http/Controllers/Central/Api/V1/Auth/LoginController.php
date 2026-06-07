<?php

namespace App\Http\Controllers\Central\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Central\Auth\LoginResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = CentralUser::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
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
