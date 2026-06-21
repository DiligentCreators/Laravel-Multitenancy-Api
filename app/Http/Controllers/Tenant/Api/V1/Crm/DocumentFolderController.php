<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\MoveDocumentFolderRequest;
use App\Http\Requests\Crm\StoreDocumentFolderRequest;
use App\Http\Requests\Crm\UpdateDocumentFolderRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\DocumentFolderResource;
use App\Models\Crm\DocumentFolder;
use App\Services\ApiResponseService;
use App\Services\Crm\DocumentActionService;
use App\Services\Crm\DocumentFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentFolderController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly DocumentFolderService $documentFolderService,
        private readonly DocumentActionService $documentActionService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', DocumentFolder::class);

        $perPage = min((int) request('per_page', 25), 100);
        $folders = $this->documentFolderService->paginateWithFilters(request()->only([
            'search', 'parent_id', 'owner_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Document folders retrieved successfully', DocumentFolderResource::collection($folders));
    }

    public function store(StoreDocumentFolderRequest $request): JsonResponse
    {
        Gate::authorize('create', DocumentFolder::class);

        $folder = $this->documentFolderService->create($request->validated());

        return $this->api->success('Document folder created successfully', new DocumentFolderResource($folder), 201);
    }

    public function show(DocumentFolder $documentFolder): JsonResponse
    {
        Gate::authorize('view', $documentFolder);

        $folder = $this->documentFolderService->find($documentFolder->id);

        return $this->api->success('Document folder retrieved successfully', new DocumentFolderResource($folder));
    }

    public function update(UpdateDocumentFolderRequest $request, DocumentFolder $documentFolder): JsonResponse
    {
        Gate::authorize('update', $documentFolder);

        $folder = $this->documentFolderService->update($documentFolder, $request->validated());

        return $this->api->success('Document folder updated successfully', new DocumentFolderResource($folder));
    }

    public function destroy(DocumentFolder $documentFolder): JsonResponse
    {
        Gate::authorize('delete', $documentFolder);

        $this->documentFolderService->delete($documentFolder);

        return $this->api->success('Document folder deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', DocumentFolder::class);

        $this->documentFolderService->restore($id);

        return $this->api->success('Document folder restored successfully');
    }

    public function move(MoveDocumentFolderRequest $request, DocumentFolder $documentFolder): JsonResponse
    {
        Gate::authorize('update', $documentFolder);

        $folder = $this->documentActionService->moveFolder($documentFolder, $request->validated()['parent_id'] ?? null);

        return $this->api->success('Document folder moved successfully', new DocumentFolderResource($folder));
    }
}
