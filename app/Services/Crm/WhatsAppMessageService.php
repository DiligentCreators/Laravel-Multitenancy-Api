<?php

namespace App\Services\Crm;

use App\Enums\WhatsAppMessageDirectionEnum;
use App\Enums\WhatsAppMessageStatusEnum;
use App\Models\Crm\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WhatsAppMessageService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'conversation_id', 'person_id', 'whatsapp_phone_number_id',
        'provider_message_id', 'direction', 'type', 'from_number',
        'to_number', 'status', 'sent_at', 'delivered_at', 'read_at',
        'failed_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return WhatsAppMessage::query()
            ->with(['conversation', 'person', 'whatsappPhoneNumber'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = WhatsAppMessage::search($search)->keys();
            $query->whereIn((new WhatsAppMessage)->getQualifiedKeyName(), $ids);
        }

        if ($conversationId = $filters['conversation_id'] ?? null) {
            $query->where('conversation_id', $conversationId);
        }

        if ($personId = $filters['person_id'] ?? null) {
            $query->where('person_id', $personId);
        }

        if ($direction = $filters['direction'] ?? null) {
            $query->where('direction', $direction);
        }

        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
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

    public function find(int $id): WhatsAppMessage
    {
        return WhatsAppMessage::with(['conversation', 'person', 'whatsappPhoneNumber'])
            ->findOrFail($id);
    }

    public function persistInbound(array $data): WhatsAppMessage
    {
        $data['direction'] = WhatsAppMessageDirectionEnum::INBOUND;
        $data['status'] = $data['status'] ?? WhatsAppMessageStatusEnum::DELIVERED;
        $data['tenant_id'] = $data['tenant_id'] ?? tenant()?->id;

        $message = WhatsAppMessage::create($data);

        $this->eventDispatcher->record($message, 'whatsapp.message_received', 'WhatsApp Message Received', $message->content, [
            'from' => $message->from_number,
            'type' => $message->type->value,
        ], Auth::id());

        return $message;
    }

    public function persistOutbound(array $data): WhatsAppMessage
    {
        $data['direction'] = WhatsAppMessageDirectionEnum::OUTBOUND;
        $data['status'] = $data['status'] ?? WhatsAppMessageStatusEnum::PENDING;
        $data['tenant_id'] = $data['tenant_id'] ?? tenant()?->id;

        $message = WhatsAppMessage::create($data);

        $this->eventDispatcher->record($message, 'whatsapp.message_sent', 'WhatsApp Message Sent', $message->content, [
            'to' => $message->to_number,
            'type' => $message->type->value,
        ], Auth::id());

        return $message;
    }

    public function updateStatus(WhatsAppMessage $message, WhatsAppMessageStatusEnum $status, ?array $timestamps = null): WhatsAppMessage
    {
        $updateData = ['status' => $status];

        if ($timestamps !== null) {
            if (isset($timestamps['sent_at'])) {
                $updateData['sent_at'] = $timestamps['sent_at'];
            }
            if (isset($timestamps['delivered_at'])) {
                $updateData['delivered_at'] = $timestamps['delivered_at'];
            }
            if (isset($timestamps['read_at'])) {
                $updateData['read_at'] = $timestamps['read_at'];
            }
            if (isset($timestamps['failed_at'])) {
                $updateData['failed_at'] = $timestamps['failed_at'];
            }
        }

        $message->update($updateData);
        $message->refresh();

        $eventType = match ($status) {
            WhatsAppMessageStatusEnum::DELIVERED => 'whatsapp.message_delivered',
            WhatsAppMessageStatusEnum::READ => 'whatsapp.message_read',
            WhatsAppMessageStatusEnum::FAILED => 'whatsapp.message_failed',
            default => null,
        };

        if ($eventType) {
            $this->eventDispatcher->record($message, $eventType, 'WhatsApp Message '.ucfirst($status->value), null, [
                'provider_message_id' => $message->provider_message_id,
            ], Auth::id());
        }

        return $message;
    }
}
