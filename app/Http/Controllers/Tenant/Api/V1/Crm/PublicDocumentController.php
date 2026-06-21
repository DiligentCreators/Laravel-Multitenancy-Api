<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AccessDocumentShareRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\DocumentResource;
use App\Services\ApiResponseService;
use App\Services\Crm\DocumentShareService;
use Illuminate\Http\JsonResponse;

class PublicDocumentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly DocumentShareService $documentShareService,
    ) {
        parent::__construct($api);
    }

    public function access(string $token, AccessDocumentShareRequest $request): JsonResponse
    {
        $share = $this->documentShareService->findByToken($token);

        if (! $share) {
            return $this->api->error('Share link not found', 404);
        }

        $document = $this->documentShareService->access($share, $request->input('password'));

        if (! $document) {
            return $this->api->error('Share link is expired or password is incorrect', 403);
        }

        return $this->api->success('Document retrieved successfully', new DocumentResource($document));
    }
}
