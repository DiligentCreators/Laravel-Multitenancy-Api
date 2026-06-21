<?php

namespace App\Services\Crm;

use App\Models\Crm\Conversation;
use App\Models\Crm\ConversationParticipant;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'uuid', 'subject', 'channel', 'status',
        'last_message_at', 'owner_id', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Conversation::query()
            ->with(['participants'])
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Conversation::search($search)->keys();
            $query->whereIn((new Conversation)->getQualifiedKeyName(), $ids);
        }

        if ($channel = $filters['channel'] ?? null) {
            $query->where('channel', $channel);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($participantType = $filters['participant_type'] ?? null) {
            $query->whereHas('participants', function (Builder $q) use ($participantType, $filters) {
                $q->where('participant_type', $participantType);
                if ($participantId = $filters['participant_id'] ?? null) {
                    $q->where('participant_id', $participantId);
                }
            });
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
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

    public function find(int $id): Conversation
    {
        return Conversation::with(['participants', 'messages'])
            ->findOrFail($id);
    }

    public function create(array $data): Conversation
    {
        return DB::transaction(function () use ($data) {
            $participants = $data['participants'] ?? [];
            unset($data['participants']);

            $data['uuid'] = (string) Str::uuid();
            $data['status'] = $data['status'] ?? 'open';
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $conversation = Conversation::create($data);

            foreach ($participants as $participant) {
                $typeMap = [
                    'person' => Person::class,
                    'organization' => Organization::class,
                    'user' => User::class,
                ];

                ConversationParticipant::create([
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                    'participant_type' => $typeMap[$participant['type']] ?? $participant['type'],
                    'participant_id' => $participant['id'],
                    'is_primary' => $participant['is_primary'] ?? false,
                ]);
            }

            $conversation->load('participants');

            $this->eventDispatcher->record($conversation, 'conversation.created', 'Conversation Created', $conversation->subject, [
                'channel' => $conversation->channel->value,
            ], Auth::id());

            return $conversation;
        });
    }

    public function update(Conversation $conversation, array $data): Conversation
    {
        return DB::transaction(function () use ($conversation, $data) {
            $wasClosed = $conversation->status?->value === 'closed';

            $data['updated_by'] = Auth::id();
            $conversation->update($data);

            $conversation->refresh();

            if (! $wasClosed && $conversation->status?->value === 'closed') {
                $this->eventDispatcher->record($conversation, 'conversation.closed', 'Conversation Closed', $conversation->subject, null, Auth::id());
            } else {
                $this->eventDispatcher->record($conversation, 'conversation.updated', 'Conversation Updated', $conversation->subject, null, Auth::id());
            }

            return $conversation;
        });
    }

    public function delete(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $this->eventDispatcher->record($conversation, 'conversation.deleted', 'Conversation Deleted', $conversation->subject, null, Auth::id());
            $conversation->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            Conversation::withTrashed()->findOrFail($id)->restore();
            $conversation = Conversation::withTrashed()->find($id);

            if ($conversation) {
                $this->eventDispatcher->record($conversation, 'conversation.restored', 'Conversation Restored', $conversation->subject, null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        Conversation::withTrashed()->findOrFail($id)->forceDelete();
    }

    public function close(Conversation $conversation): Conversation
    {
        return $this->update($conversation, ['status' => 'closed']);
    }
}
