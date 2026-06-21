<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreMessageTemplateRequest;
use App\Http\Requests\Crm\UpdateMessageTemplateRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\MessageTemplateResource;
use App\Models\Crm\MessageTemplate;
use App\Services\ApiResponseService;
use App\Services\Crm\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MessageTemplateController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly MessageTemplateService $messageTemplateService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $perPage = min((int) request('per_page', 25), 100);
        $templates = $this->messageTemplateService->paginateWithFilters(request()->only([
            'search', 'channel', 'category', 'is_active', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Message templates retrieved successfully', MessageTemplateResource::collection($templates));
    }

    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', MessageTemplate::class);

        $template = $this->messageTemplateService->create($request->validated());

        return $this->api->success('Message template created successfully', new MessageTemplateResource($template), 201);
    }

    public function show(MessageTemplate $messageTemplate): JsonResponse
    {
        Gate::authorize('view', $messageTemplate);

        $template = $this->messageTemplateService->find($messageTemplate->id);

        return $this->api->success('Message template retrieved successfully', new MessageTemplateResource($template));
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): JsonResponse
    {
        Gate::authorize('update', $messageTemplate);

        $template = $this->messageTemplateService->update($messageTemplate, $request->validated());

        return $this->api->success('Message template updated successfully', new MessageTemplateResource($template));
    }

    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        Gate::authorize('delete', $messageTemplate);

        $this->messageTemplateService->delete($messageTemplate);

        return $this->api->success('Message template deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', MessageTemplate::class);

        $this->messageTemplateService->restore($id);

        return $this->api->success('Message template restored successfully');
    }
}
