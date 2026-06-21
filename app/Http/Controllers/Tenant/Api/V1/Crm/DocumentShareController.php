<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreDocumentShareRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\DocumentShareResource;
use App\Models\Crm\Document;
use App\Models\Crm\DocumentShare;
use App\Services\ApiResponseService;
use App\Services\Crm\DocumentShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentShareController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly DocumentShareService $documentShareService,
    ) {
        parent::__construct($api);
    }

    public function index(int $documentId): JsonResponse
    {
        Gate::authorize('viewAny', DocumentShare::class);

        $perPage = min((int) request('per_page', 25), 100);
        $shares = $this->documentShareService->paginate($documentId, $perPage);

        return $this->api->success('Document shares retrieved successfully', DocumentShareResource::collection($shares));
    }

    public function store(StoreDocumentShareRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('create', DocumentShare::class);

        $share = $this->documentShareService->create($document, $request->validated());

        return $this->api->success('Document share created successfully', new DocumentShareResource($share), 201);
    }

    public function show(DocumentShare $documentShare): JsonResponse
    {
        Gate::authorize('view', $documentShare);

        $share = $this->documentShareService->find($documentShare->id);

        return $this->api->success('Document share retrieved successfully', new DocumentShareResource($share));
    }

    public function destroy(DocumentShare $documentShare): JsonResponse
    {
        Gate::authorize('delete', $documentShare);

        $this->documentShareService->delete($documentShare);

        return $this->api->success('Document share deleted successfully');
    }
}
