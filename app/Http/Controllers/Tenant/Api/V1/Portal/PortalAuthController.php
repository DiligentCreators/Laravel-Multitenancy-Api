<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\PortalUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = PortalUser::where('email', $request->email)
            ->when(tenancy()->initialized, fn ($q) => $q->where('tenant_id', tenant()->id))
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->api->error('The provided credentials are incorrect.', 403);
        }

        if ($user->trashed()) {
            return $this->api->error('The provided credentials are incorrect.', 403);
        }

        if (! $user->is_active) {
            return $this->api->error('The provided credentials are incorrect.', 403);
        }

        $token = $user->createToken('portal-token')->plainTextToken;

        return $this->api->success('Login successful', [
            'token' => $token,
            'type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->api->success('Logout successful');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker('portal_users')->sendResetLink(
            $request->only('email')
        );

        return $this->api->success('If the email address you entered is registered with us, we will send you an email with instructions about how to reset your password.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('portal_users')->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'registered_at' => $user->registered_at ?? now(),
                ])->save();

                $user->setRememberToken(Str::random(60));

                $user->tokens()->delete();
            }
        );

        return match ($status) {
            Password::PASSWORD_RESET => $this->api->success('Your password has been reset successfully.'),
            Password::INVALID_USER => $this->api->error('We cannot find a user with that email address.'),
            Password::INVALID_TOKEN => $this->api->error('This password reset token is invalid.'),
            default => $this->api->error('Failed to reset password. Please try again.'),
        };
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->api->error('Current password is incorrect.', 403);
        }

        $user->update([
            'password' => $request->password,
        ]);

        return $this->api->success('Password changed successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->api->success('Profile retrieved successfully', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
        ]);
    }
}
