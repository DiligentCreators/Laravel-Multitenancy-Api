<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreMessageRequest;
use App\Http\Requests\Crm\UpdateMessageRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\MessageResource;
use App\Models\Crm\Conversation;
use App\Models\Crm\Message;
use App\Services\ApiResponseService;
use App\Services\Crm\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly MessageService $messageService,
    ) {
        parent::__construct($api);
    }

    public function index(int $conversationId): JsonResponse
    {
        Gate::authorize('viewAny', Message::class);

        $perPage = min((int) request('per_page', 25), 100);
        $messages = $this->messageService->paginateWithFilters($conversationId, request()->only([
            'search', 'direction', 'status', 'sender_type', 'sender_id', 'from_date', 'to_date', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Messages retrieved successfully', MessageResource::collection($messages));
    }

    public function store(StoreMessageRequest $request, int $conversationId): JsonResponse
    {
        Gate::authorize('create', Message::class);

        $message = $this->messageService->create($conversationId, $request->validated());

        return $this->api->success('Message created successfully', new MessageResource($message), 201);
    }

    public function show(Conversation $conversation, Message $message): JsonResponse
    {
        abort_if($message->conversation_id !== $conversation->id, 404);

        Gate::authorize('view', $message);

        $message = $this->messageService->find($message->id);

        return $this->api->success('Message retrieved successfully', new MessageResource($message));
    }

    public function update(UpdateMessageRequest $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_if($message->conversation_id !== $conversation->id, 404);

        Gate::authorize('update', $message);

        $message = $this->messageService->update($message, $request->validated());

        return $this->api->success('Message updated successfully', new MessageResource($message));
    }

    public function destroy(Conversation $conversation, Message $message): JsonResponse
    {
        abort_if($message->conversation_id !== $conversation->id, 404);

        Gate::authorize('delete', $message);

        $this->messageService->delete($message);

        return $this->api->success('Message deleted successfully');
    }
}
