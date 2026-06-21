<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCustomFieldRequest;
use App\Http\Requests\Crm\UpdateCustomFieldRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\CustomFieldDefinitionResource;
use App\Models\Crm\CustomFieldDefinition;
use App\Services\ApiResponseService;
use App\Services\Crm\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomFieldController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly CustomFieldService $customFieldService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CustomFieldDefinition::class);

        $entityType = request('entity_type');
        $perPage = min((int) request('per_page', 50), 100);
        $fields = $this->customFieldService->paginate($entityType, $perPage);

        return $this->api->success('Custom fields retrieved successfully', CustomFieldDefinitionResource::collection($fields));
    }

    public function store(StoreCustomFieldRequest $request): JsonResponse
    {
        Gate::authorize('create', CustomFieldDefinition::class);

        $field = $this->customFieldService->create($request->validated());

        return $this->api->success('Custom field created successfully', new CustomFieldDefinitionResource($field), 201);
    }

    public function show(CustomFieldDefinition $customField): JsonResponse
    {
        Gate::authorize('view', $customField);

        return $this->api->success('Custom field retrieved successfully', new CustomFieldDefinitionResource($customField));
    }

    public function update(UpdateCustomFieldRequest $request, CustomFieldDefinition $customField): JsonResponse
    {
        Gate::authorize('update', $customField);

        $field = $this->customFieldService->update($customField, $request->validated());

        return $this->api->success('Custom field updated successfully', new CustomFieldDefinitionResource($field));
    }

    public function destroy(CustomFieldDefinition $customField): JsonResponse
    {
        Gate::authorize('delete', $customField);

        $this->customFieldService->delete($customField);

        return $this->api->success('Custom field deleted successfully');
    }
}
