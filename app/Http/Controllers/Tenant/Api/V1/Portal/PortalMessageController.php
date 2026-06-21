<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\ConversationParticipant;
use App\Models\Crm\Message;
use App\Models\Crm\Person;
use App\Models\Crm\PortalUser;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalMessageController extends Controller
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

        $conversationIds = ConversationParticipant::where('participant_type', (new Person)->getMorphClass())
            ->whereIn('participant_id', $personIds)
            ->pluck('conversation_id');

        $perPage = min((int) $request->get('per_page', 25), 100);

        $messages = Message::whereIn('conversation_id', $conversationIds)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->api->success('Messages retrieved successfully', $messages);
    }

    public function show(Message $message, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $hasAccess = ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->where('participant_type', (new Person)->getMorphClass())
            ->whereIn('participant_id', $personIds)
            ->exists();

        if (! $hasAccess) {
            return $this->api->error('Message not found.', 404);
        }

        return $this->api->success('Message retrieved successfully', $message);
    }
}
