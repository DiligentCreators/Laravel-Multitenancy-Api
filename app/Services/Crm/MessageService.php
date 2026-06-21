<?php

namespace App\Services\Crm;

use App\Models\Crm\Message;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'conversation_id', 'sender_type', 'sender_id',
        'direction', 'body', 'status', 'sent_at', 'delivered_at',
        'read_at', 'owner_id', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Message::query()
            ->with(['sender', 'attachments'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(int $conversationId, array $filters = [], int $perPage = 25)
    {
        $query = $this->query()->where('conversation_id', $conversationId);

        if ($search = $filters['search'] ?? null) {
            $ids = Message::search($search)->keys();
            $query->whereIn((new Message)->getQualifiedKeyName(), $ids);
        }

        if ($direction = $filters['direction'] ?? null) {
            $query->where('direction', $direction);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($senderType = $filters['sender_type'] ?? null) {
            $query->where('sender_type', $senderType);
            if ($senderId = $filters['sender_id'] ?? null) {
                $query->where('sender_id', $senderId);
            }
        }

        if ($fromDate = $filters['from_date'] ?? null) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate = $filters['to_date'] ?? null) {
            $query->where('created_at', '<=', $toDate);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Message
    {
        return Message::with(['sender', 'attachments', 'conversation'])
            ->findOrFail($id);
    }

    public function create(int $conversationId, array $data): Message
    {
        return DB::transaction(function () use ($conversationId, $data) {
            $typeMap = [
                'user' => User::class,
                'person' => Person::class,
                'organization' => Organization::class,
            ];

            $data['direction'] = $data['direction'] ?? 'outbound';
            $data['status'] = $data['status'] ?? 'sent';
            $senderType = $data['sender_type'] ?? 'user';
            $data['sender_type'] = $typeMap[$senderType] ?? $senderType;
            $data['conversation_id'] = $conversationId;
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $message = Message::create($data);

            $conversation = $message->conversation;
            if ($conversation) {
                $conversation->update(['last_message_at' => now()]);
            }

            $eventType = $data['direction'] === 'inbound' ? 'message.received' : 'message.sent';
            $eventTitle = $data['direction'] === 'inbound' ? 'Message Received' : 'Message Sent';

            $this->eventDispatcher->record($message, $eventType, $eventTitle, null, [
                'conversation_id' => $conversationId,
                'direction' => $data['direction'],
            ], Auth::id());

            return $message;
        });
    }

    public function update(Message $message, array $data): Message
    {
        return DB::transaction(function () use ($message, $data) {
            $wasRead = $message->read_at !== null;

            $data['updated_by'] = Auth::id();
            $message->update($data);

            $message->refresh();

            if (! $wasRead && $message->read_at !== null) {
                $this->eventDispatcher->record($message, 'message.read', 'Message Read', null, [
                    'conversation_id' => $message->conversation_id,
                ], Auth::id());
            }

            return $message;
        });
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }
}
