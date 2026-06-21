<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreDocumentVersionRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\DocumentVersionResource;
use App\Models\Crm\Document;
use App\Models\Crm\DocumentVersion;
use App\Services\ApiResponseService;
use App\Services\Crm\DocumentService;
use App\Services\Crm\DocumentStorageService;
use App\Services\Crm\DocumentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class DocumentVersionController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly DocumentService $documentService,
        private readonly DocumentVersionService $documentVersionService,
        private readonly DocumentStorageService $storageService,
    ) {
        parent::__construct($api);
    }

    public function index(int $documentId): JsonResponse
    {
        Gate::authorize('viewAny', DocumentVersion::class);

        $perPage = min((int) request('per_page', 25), 100);
        $versions = $this->documentVersionService->paginate($documentId, $perPage);

        return $this->api->success('Document versions retrieved successfully', DocumentVersionResource::collection($versions));
    }

    public function store(StoreDocumentVersionRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('create', DocumentVersion::class);

        $version = $this->documentService->createVersion($document, $request->validated());

        return $this->api->success('Document version created successfully', new DocumentVersionResource($version), 201);
    }

    public function show(DocumentVersion $documentVersion): JsonResponse
    {
        Gate::authorize('view', Document::class);

        $version = $this->documentVersionService->find($documentVersion->id);

        return $this->api->success('Document version retrieved successfully', new DocumentVersionResource($version));
    }

    public function destroy(DocumentVersion $documentVersion): JsonResponse
    {
        Gate::authorize('delete', DocumentVersion::class);

        $this->documentVersionService->delete($documentVersion);

        return $this->api->success('Document version deleted successfully');
    }

    public function download(DocumentVersion $documentVersion): JsonResponse
    {
        $document = $documentVersion->document;

        Gate::authorize('view', $document);

        $url = URL::temporarySignedRoute(
            'tenant.crm.documents.versions.serve',
            now()->addMinutes(30),
            ['documentVersion' => $documentVersion->id]
        );

        return $this->api->success('Download URL generated successfully', ['url' => $url]);
    }

    public function serve(Request $request, DocumentVersion $documentVersion)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired download link.');
        }

        $document = $documentVersion->document;

        Gate::authorize('view', $document);

        $this->documentService->recordVersionDownload($document, $documentVersion);

        return $this->storageService->download($documentVersion->file_path, $documentVersion->file_name);
    }
}
