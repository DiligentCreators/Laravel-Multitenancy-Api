<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\User\ChangeUserPasswordRequest;
use App\Http\Requests\Central\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Central\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Central\Api\V1\User\ListUserResource;
use App\Http\Resources\Central\Api\V1\User\UserResource;
use App\Models\CentralUser;
use App\Services\ApiResponseService;
use App\Services\Central\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly UserService $userService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CentralUser::class);

        $users = $this->userService->paginate(
            request(),
            $this->perPage(request()),
            Auth::id(),
        );

        return $this->api->success(
            'users retrieved successfully',
            ListUserResource::collection($users),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', CentralUser::class);

        $user = $this->userService->create($request->validated());

        return $this->api->success(
            'User has been created successfully',
            new UserResource($user),
            201,
        );
    }

    public function show(CentralUser $user): JsonResponse
    {
        Gate::authorize('view', $user);

        if ($user->trashed()) {
            return $this->api->notFound('User has been deleted.');
        }

        return $this->api->success(
            'User retrieved successfully',
            new UserResource($user),
        );
    }

    public function update(UpdateUserRequest $request, CentralUser $user): JsonResponse
    {
        Gate::authorize('update', $user);

        if ($user->trashed()) {
            return $this->api->notFound('Cannot update a deleted user.');
        }

        $this->userService->update($user, $request->validated());

        return $this->api->success(
            'User has been updated successfully',
            new UserResource($user),
        );
    }

    public function destroy(CentralUser $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->trashed()) {
            return $this->api->notFound('User is already deleted.');
        }

        $user->delete();

        return $this->api->success(
            'User has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(CentralUser $user): JsonResponse
    {
        Gate::authorize('restore', $user);

        if (! $user->trashed()) {
            return $this->api->notFound('User is not deleted.');
        }

        $user->restore();

        return $this->api->success(
            'User has been restored successfully',
            new UserResource($user),
        );
    }

    public function forceDelete(CentralUser $user): JsonResponse
    {
        Gate::authorize('forceDelete', $user);

        if (! $user->trashed()) {
            return $this->api->error('User must be deleted before force deleting.', 400);
        }

        $user->forceDelete();

        return $this->api->success(
            'User has been force deleted successfully',
            null,
            200,
        );
    }

    public function changePassword(ChangeUserPasswordRequest $request, CentralUser $user): JsonResponse
    {
        Gate::authorize('update', $user);

        if ($user->trashed()) {
            return $this->api->notFound('Cannot update a deleted user.');
        }

        $this->userService->changePassword($user, $request->validated()['password']);

        return $this->api->success(
            'Password has been changed successfully',
            new UserResource($user),
        );
    }

    public function suspend(CentralUser $user): JsonResponse
    {
        Gate::authorize('suspend', $user);

        if ($user->trashed()) {
            return $this->api->notFound('Cannot suspend a deleted user.');
        }

        $this->userService->suspend($user);

        return $this->api->success(
            'User has been suspended successfully',
            new UserResource($user),
        );
    }

    public function unsuspend(CentralUser $user): JsonResponse
    {
        Gate::authorize('unsuspend', $user);

        if ($user->trashed()) {
            return $this->api->notFound('Cannot unsuspend a deleted user.');
        }

        $this->userService->unsuspend($user);

        return $this->api->success(
            'User has been unsuspended successfully',
            new UserResource($user),
        );
    }
}
