<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\SettingDefinition\StoreSettingDefinitionRequest;
use App\Http\Requests\Central\Api\V1\SettingDefinition\UpdateSettingDefinitionRequest;
use App\Http\Resources\Central\Api\V1\SettingDefinition\ListSettingDefinitionResource;
use App\Http\Resources\Central\Api\V1\SettingDefinition\SettingDefinitionResource;
use App\Models\SettingDefinition;
use App\Services\ApiResponseService;
use App\Services\Central\SettingDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingDefinitionController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SettingDefinitionService $settingDefinitionService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', SettingDefinition::class);

        $settingDefinitions = $this->settingDefinitionService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'settingDefinitions retrieved successfully',
            ListSettingDefinitionResource::collection($settingDefinitions),
        );
    }

    public function store(StoreSettingDefinitionRequest $request): JsonResponse
    {
        Gate::authorize('create', SettingDefinition::class);

        $settingDefinition = $this->settingDefinitionService->create($request->validated());

        return $this->api->success(
            'SettingDefinition has been created successfully',
            new SettingDefinitionResource($settingDefinition),
            201,
        );
    }

    public function update(UpdateSettingDefinitionRequest $request, SettingDefinition $settingDefinition): JsonResponse
    {
        Gate::authorize('update', $settingDefinition);

        $this->settingDefinitionService->update($settingDefinition, $request->validated());

        return $this->api->success(
            'SettingDefinition has been updated successfully',
            new SettingDefinitionResource($settingDefinition),
        );
    }
}
