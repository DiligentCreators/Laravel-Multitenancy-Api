<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\WhatsAppMessageResource;
use App\Models\Crm\WhatsAppMessage;
use App\Services\ApiResponseService;
use App\Services\Crm\WhatsAppMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WhatsAppMessageController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly WhatsAppMessageService $whatsAppMessageService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', WhatsAppMessage::class);

        $perPage = min((int) request('per_page', 25), 100);
        $messages = $this->whatsAppMessageService->paginateWithFilters(request()->only([
            'search', 'conversation_id', 'person_id', 'direction', 'type', 'status',
            'from_date', 'to_date', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('WhatsApp messages retrieved successfully', WhatsAppMessageResource::collection($messages));
    }

    public function show(WhatsAppMessage $whatsAppMessage): JsonResponse
    {
        Gate::authorize('view', $whatsAppMessage);

        $message = $this->whatsAppMessageService->find($whatsAppMessage->id);

        return $this->api->success('WhatsApp message retrieved successfully', new WhatsAppMessageResource($message));
    }
}
