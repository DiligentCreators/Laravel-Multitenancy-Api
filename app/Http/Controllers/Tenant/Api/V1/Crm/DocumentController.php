<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\MoveDocumentRequest;
use App\Http\Requests\Crm\StoreDocumentRequest;
use App\Http\Requests\Crm\UpdateDocumentRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\DocumentResource;
use App\Models\Crm\Document;
use App\Services\ApiResponseService;
use App\Services\Crm\DocumentActionService;
use App\Services\Crm\DocumentService;
use App\Services\Crm\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class DocumentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly DocumentService $documentService,
        private readonly DocumentActionService $documentActionService,
        private readonly DocumentStorageService $storageService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $perPage = min((int) request('per_page', 25), 100);
        $documents = $this->documentService->paginateWithFilters(request()->only([
            'search', 'folder_id', 'status', 'mime_type', 'extension', 'is_locked',
            'documentable_type', 'documentable_id', 'owner_id', 'from_date', 'to_date',
            'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Documents retrieved successfully', DocumentResource::collection($documents));
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $document = $this->documentService->create($request->validated());

        return $this->api->success('Document created successfully', new DocumentResource($document), 201);
    }

    public function show(Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $document = $this->documentService->find($document->id);

        return $this->api->success('Document retrieved successfully', new DocumentResource($document));
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentService->update($document, $request->validated());

        return $this->api->success('Document updated successfully', new DocumentResource($document));
    }

    public function destroy(Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        $this->documentService->delete($document);

        return $this->api->success('Document deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $this->documentService->restore($id);

        return $this->api->success('Document restored successfully');
    }

    public function lock(Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentActionService->lock($document);

        return $this->api->success('Document locked successfully', new DocumentResource($document));
    }

    public function unlock(Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentActionService->unlock($document);

        return $this->api->success('Document unlocked successfully', new DocumentResource($document));
    }

    public function move(MoveDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentActionService->move($document, $request->validated()['folder_id'] ?? null);

        return $this->api->success('Document moved successfully', new DocumentResource($document));
    }

    public function publish(Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentActionService->publish($document);

        return $this->api->success('Document published successfully', new DocumentResource($document));
    }

    public function archive(Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $document = $this->documentActionService->archive($document);

        return $this->api->success('Document archived successfully', new DocumentResource($document));
    }

    public function download(Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $url = URL::temporarySignedRoute(
            'tenant.crm.documents.serve',
            now()->addMinutes(30),
            ['document' => $document->id]
        );

        return $this->api->success('Download URL generated successfully', ['url' => $url]);
    }

    public function serve(Request $request, Document $document)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired download link.');
        }

        Gate::authorize('view', $document);

        $this->documentService->recordDownload($document);

        return $this->storageService->download($document->file_path, $document->file_name);
    }
}
