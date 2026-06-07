<?php

namespace App\Http\Controllers\Central\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('central_users')->reset(
            $validated,
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->setRememberToken(Str::random(60));

                // Revoke all existing tokens for this user
                $user->tokens()->delete();
            }
        );

        return match ($status) {
            Password::PASSWORD_RESET => $this->api->success(
                message: 'Your password has been reset successfully.',
            ),
            Password::INVALID_USER => $this->api->error(
                message: 'We cannot find a user with that email address.',
            ),
            Password::INVALID_TOKEN => $this->api->error(
                message: 'This password reset token is invalid.',
            ),
            default => $this->api->error(
                message: 'Failed to reset password. Please try again.',
            ),
        };
    }
}
