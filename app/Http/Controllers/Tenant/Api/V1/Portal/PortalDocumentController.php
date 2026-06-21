<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\Document;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\PortalUser;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalDocumentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');
        $orgIds = $user->personLinks()->whereNotNull('organization_id')->pluck('organization_id');

        $perPage = min((int) $request->get('per_page', 25), 100);

        $documents = Document::where(function ($q) use ($personIds, $orgIds) {
            $q->where(function ($q) use ($personIds) {
                $q->where('documentable_type', (new Person)->getMorphClass())
                    ->whereIn('documentable_id', $personIds);
            })->orWhere(function ($q) use ($orgIds) {
                $q->where('documentable_type', (new Organization)->getMorphClass())
                    ->whereIn('documentable_id', $orgIds);
            });
        })->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->api->success('Documents retrieved successfully', $documents);
    }

    public function show(Document $document, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();

        if (! $this->userCanAccessDocument($user, $document)) {
            return $this->api->error('Document not found.', 404);
        }

        return $this->api->success('Document retrieved successfully', $document);
    }

    private function userCanAccessDocument(PortalUser $user, Document $document): bool
    {
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');
        $orgIds = $user->personLinks()->whereNotNull('organization_id')->pluck('organization_id');

        if ($document->documentable_type === (new Person)->getMorphClass()) {
            return $personIds->contains($document->documentable_id);
        }

        if ($document->documentable_type === (new Organization)->getMorphClass()) {
            return $orgIds->contains($document->documentable_id);
        }

        return false;
    }
}
