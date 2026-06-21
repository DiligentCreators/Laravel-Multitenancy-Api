<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreConversationRequest;
use App\Http\Requests\Crm\UpdateConversationRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\ConversationResource;
use App\Models\Crm\Conversation;
use App\Services\ApiResponseService;
use App\Services\Crm\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly ConversationService $conversationService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $perPage = min((int) request('per_page', 25), 100);
        $conversations = $this->conversationService->paginateWithFilters(request()->only([
            'search', 'channel', 'status', 'participant_type', 'participant_id', 'owner_id', 'from_date', 'to_date', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Conversations retrieved successfully', ConversationResource::collection($conversations));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        Gate::authorize('create', Conversation::class);

        $conversation = $this->conversationService->create($request->validated());

        return $this->api->success('Conversation created successfully', new ConversationResource($conversation), 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation = $this->conversationService->find($conversation->id);

        return $this->api->success('Conversation retrieved successfully', new ConversationResource($conversation));
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $conversation = $this->conversationService->update($conversation, $request->validated());

        return $this->api->success('Conversation updated successfully', new ConversationResource($conversation));
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation);

        $this->conversationService->delete($conversation);

        return $this->api->success('Conversation deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Conversation::class);

        $this->conversationService->restore($id);

        return $this->api->success('Conversation restored successfully');
    }

    public function close(Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $conversation = $this->conversationService->close($conversation);

        return $this->api->success('Conversation closed successfully', new ConversationResource($conversation));
    }
}
