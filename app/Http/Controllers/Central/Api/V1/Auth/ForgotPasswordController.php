<?php

namespace App\Http\Controllers\Central\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker('central_users')->sendResetLink(
            $validated
        );

        return match ($status) {
            Password::RESET_LINK_SENT => $this->api->success(
                message: 'If the email address you entered is registered with us, we will send you an email with instructions about how to reset your password.',
            ),
            default => $this->api->success(
                message: 'If the email address you entered is registered with us, we will send you an email with instructions about how to reset your password.',
            ),
        };
    }
}
