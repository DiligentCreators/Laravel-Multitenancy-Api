<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreSourceRequest;
use App\Http\Requests\Crm\UpdateSourceRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\SourceResource;
use App\Models\Crm\Source;
use App\Services\ApiResponseService;
use App\Services\Crm\SourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SourceController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SourceService $sourceService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Source::class);

        $perPage = min((int) request('per_page', 25), 100);
        $sources = $this->sourceService->paginate($perPage);

        return $this->api->success('Sources retrieved successfully', SourceResource::collection($sources));
    }

    public function store(StoreSourceRequest $request): JsonResponse
    {
        Gate::authorize('create', Source::class);

        $source = $this->sourceService->create($request->validated());

        return $this->api->success('Source created successfully', new SourceResource($source), 201);
    }

    public function show(Source $source): JsonResponse
    {
        Gate::authorize('view', $source);

        return $this->api->success('Source retrieved successfully', new SourceResource($source));
    }

    public function update(UpdateSourceRequest $request, Source $source): JsonResponse
    {
        Gate::authorize('update', $source);

        $source = $this->sourceService->update($source, $request->validated());

        return $this->api->success('Source updated successfully', new SourceResource($source));
    }

    public function destroy(Source $source): JsonResponse
    {
        Gate::authorize('delete', $source);

        $this->sourceService->delete($source);

        return $this->api->success('Source deleted successfully');
    }
}
