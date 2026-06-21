<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\SettingGroup\StoreSettingGroupRequest;
use App\Http\Requests\Central\Api\V1\SettingGroup\UpdateSettingGroupRequest;
use App\Http\Resources\Central\Api\V1\SettingGroup\ListSettingGroupResource;
use App\Http\Resources\Central\Api\V1\SettingGroup\SettingGroupResource;
use App\Models\SettingGroup;
use App\Services\ApiResponseService;
use App\Services\Central\SettingGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingGroupController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SettingGroupService $settingGroupService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', SettingGroup::class);

        $groups = $this->settingGroupService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'Setting groups retrieved successfully',
            ListSettingGroupResource::collection($groups),
        );
    }

    public function store(StoreSettingGroupRequest $request): JsonResponse
    {
        Gate::authorize('create', SettingGroup::class);

        $group = $this->settingGroupService->create($request->validated());

        return $this->api->success(
            'Setting group created successfully',
            new SettingGroupResource($group),
            201,
        );
    }

    public function show(SettingGroup $settingsGroup): JsonResponse
    {
        Gate::authorize('view', $settingsGroup);

        $settingsGroup->load('settings');

        return $this->api->success(
            'Setting group retrieved successfully',
            new SettingGroupResource($settingsGroup),
        );
    }

    public function update(UpdateSettingGroupRequest $request, SettingGroup $settingsGroup): JsonResponse
    {
        Gate::authorize('update', $settingsGroup);

        $this->settingGroupService->update($settingsGroup, $request->validated());

        return $this->api->success(
            'Setting group updated successfully',
            new SettingGroupResource($settingsGroup),
        );
    }

    public function destroy(SettingGroup $settingsGroup): JsonResponse
    {
        Gate::authorize('delete', $settingsGroup);

        $this->settingGroupService->delete($settingsGroup);

        return $this->api->success(
            'Setting group deleted successfully',
            null,
        );
    }
}
