<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Setting\StoreSettingRequest;
use App\Http\Requests\Central\Api\V1\Setting\UpdateSettingRequest;
use App\Http\Resources\Central\Api\V1\Setting\ListSettingResource;
use App\Http\Resources\Central\Api\V1\Setting\SettingResource;
use App\Models\Setting;
use App\Services\ApiResponseService;
use App\Services\Central\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SettingService $settingService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        $settings = $this->settingService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'Settings retrieved successfully',
            ListSettingResource::collection($settings),
        );
    }

    public function store(StoreSettingRequest $request): JsonResponse
    {
        Gate::authorize('create', Setting::class);

        $setting = $this->settingService->create($request->validated());

        return $this->api->success(
            'Setting created successfully',
            new SettingResource($setting),
            201,
        );
    }

    public function show(Setting $systemSetting): JsonResponse
    {
        Gate::authorize('view', $systemSetting);

        $systemSetting = $this->settingService->find($systemSetting->id);

        return $this->api->success(
            'Setting retrieved successfully',
            new SettingResource($systemSetting),
        );
    }

    public function update(UpdateSettingRequest $request, Setting $systemSetting): JsonResponse
    {
        Gate::authorize('update', $systemSetting);

        $this->settingService->update($systemSetting, $request->validated());

        return $this->api->success(
            'Setting updated successfully',
            new SettingResource($systemSetting),
        );
    }

    public function destroy(Setting $systemSetting): JsonResponse
    {
        Gate::authorize('delete', $systemSetting);

        $this->settingService->delete($systemSetting);

        return $this->api->success(
            'Setting deleted successfully',
            null,
        );
    }
}
