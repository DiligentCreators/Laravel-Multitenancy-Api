<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\FeatureDefinitionResource;
use App\Models\Crm\FeatureDefinition;
use App\Services\ApiResponseService;
use App\Services\Crm\FeatureDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FeatureDefinitionController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly FeatureDefinitionService $featureDefinitionService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', FeatureDefinition::class);

        $perPage = min((int) request('per_page', 100), 100);
        $definitions = $this->featureDefinitionService->paginate($perPage);

        return $this->api->success(
            'Feature definitions retrieved successfully',
            FeatureDefinitionResource::collection($definitions)
        );
    }

    public function show(FeatureDefinition $featureDefinition): JsonResponse
    {
        Gate::authorize('view', $featureDefinition);

        return $this->api->success('Feature definition retrieved successfully', new FeatureDefinitionResource($featureDefinition));
    }
}
