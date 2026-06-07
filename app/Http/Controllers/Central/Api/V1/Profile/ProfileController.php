<?php

namespace App\Http\Controllers\Central\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Profile\ChangePasswordRequest;
use App\Http\Requests\Central\Profile\UpdateProfileRequest;
use App\Http\Resources\Central\Profile\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = request()->user();

        return $this->api->success(
            message: 'Profile data retrieved',
            data: new ProfileResource($user)
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = request()->user();

        $validated = $request->validated();

        $user->update($validated);

        return $this->api->success(
            message: 'Profile data updated',
            data: new ProfileResource($user)
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = request()->user();

        $validated = $request->validated();

        // old password and new password cannot be same
        if ($validated['old_password'] === $validated['password']) {
            return $this->api->error(
                message: 'Old password and new password cannot be same',
            );
        }

        // Check old password
        if (! Hash::check($validated['old_password'], $user->password)) {
            return $this->api->error(
                message: 'Old password is incorrect',
            );
        }

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        return $this->api->success(
            message: 'Password change successful',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->api->success(
            message: 'Logout successful',
        );
    }
}
