<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BulkTagRequest;
use App\Http\Requests\Crm\MergeTagsRequest;
use App\Http\Requests\Crm\StoreTagRequest;
use App\Http\Requests\Crm\UpdateTagRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\TagResource;
use App\Models\Crm\Tag;
use App\Services\ApiResponseService;
use App\Services\Crm\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TagService $tagService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Tag::class);

        $perPage = min((int) request('per_page', 25), 100);
        $tags = $this->tagService->paginate($perPage);

        return $this->api->success('Tags retrieved successfully', TagResource::collection($tags));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        Gate::authorize('create', Tag::class);

        $tag = $this->tagService->create($request->validated());

        return $this->api->success('Tag created successfully', new TagResource($tag), 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        Gate::authorize('view', $tag);

        return $this->api->success('Tag retrieved successfully', new TagResource($tag));
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $tag);

        $tag = $this->tagService->update($tag, $request->validated());

        return $this->api->success('Tag updated successfully', new TagResource($tag));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        Gate::authorize('delete', $tag);

        $this->tagService->delete($tag);

        return $this->api->success('Tag deleted successfully');
    }

    public function merge(MergeTagsRequest $request): JsonResponse
    {
        Gate::authorize('update', Tag::class);

        $source = $this->tagService->find($request->input('source_id'));
        $target = $this->tagService->find($request->input('target_id'));

        $this->tagService->merge($source, $target);

        return $this->api->success('Tags merged successfully', new TagResource($target));
    }

    public function bulkAttach(BulkTagRequest $request): JsonResponse
    {
        Gate::authorize('update', Tag::class);

        $this->tagService->bulkAttach(
            $request->input('entity_type'),
            $request->input('entity_ids'),
            $request->input('tag_ids')
        );

        return $this->api->success('Tags attached successfully');
    }
}
