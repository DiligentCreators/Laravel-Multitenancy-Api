<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\Conversation;
use App\Models\Crm\Person;
use App\Models\Crm\PortalUser;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalConversationController extends Controller
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

        $perPage = min((int) $request->get('per_page', 25), 100);

        $conversations = Conversation::whereHas('participants', function ($q) use ($personIds) {
            $q->where('participant_type', (new Person)->getMorphClass())
                ->whereIn('participant_id', $personIds);
        })->orderBy('last_message_at', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->api->success('Conversations retrieved successfully', $conversations);
    }

    public function show(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $hasAccess = $conversation->participants()
            ->where('participant_type', (new Person)->getMorphClass())
            ->whereIn('participant_id', $personIds)
            ->exists();

        if (! $hasAccess) {
            return $this->api->error('Conversation not found.', 404);
        }

        return $this->api->success('Conversation retrieved successfully', $conversation->load('participants'));
    }
}
