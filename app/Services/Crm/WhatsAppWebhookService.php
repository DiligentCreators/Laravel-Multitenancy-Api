<?php

namespace App\Services\Crm;

use App\Enums\ConversationChannelEnum;
use App\Enums\ConversationStatusEnum;
use App\Enums\WhatsAppMessageStatusEnum;
use App\Enums\WhatsAppMessageTypeEnum;
use App\Models\Crm\Conversation;
use App\Models\Crm\ConversationParticipant;
use App\Models\Crm\Person;
use App\Models\Crm\WhatsAppAccount;
use App\Models\Crm\WhatsAppMessage;
use App\Models\Crm\WhatsAppPhoneNumber;
use App\Models\Crm\WhatsAppWebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppWebhookService
{
    public function __construct(
        private readonly WhatsAppMessageService $messageService,
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function verifyChallenge(string $mode, ?string $verifyToken, string $challenge, WhatsAppAccount $account): ?string
    {
        if ($mode === 'subscribe' && $verifyToken === $account->webhook_verify_token) {
            return $challenge;
        }

        return null;
    }

    public function processPayload(array $payload, WhatsAppAccount $account): void
    {
        $this->storeWebhookLog($account, 'webhook_received', $payload);

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];
                $statuses = $value['statuses'] ?? [];

                foreach ($messages as $messageData) {
                    $this->processInboundMessage($messageData, $value, $account);
                }

                foreach ($statuses as $statusData) {
                    $this->processStatusUpdate($statusData, $account);
                }
            }
        }
    }

    private function processInboundMessage(array $messageData, array $value, WhatsAppAccount $account): void
    {
        DB::transaction(function () use ($messageData, $value, $account) {
            $fromNumber = $messageData['from'] ?? '';
            $toNumber = $value['metadata']['display_phone_number'] ?? '';
            $messageId = $messageData['id'] ?? Str::uuid();

            $phoneNumber = WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('display_phone_number', $toNumber)
                ->first();

            if (! $phoneNumber) {
                return;
            }

            $messageType = $this->resolveMessageType($messageData);
            $content = $this->extractContent($messageData, $messageType);
            $mediaUrl = $this->extractMediaUrl($messageData, $messageType);

            $person = Person::where(function ($q) use ($fromNumber) {
                $q->where('phone', $fromNumber)
                    ->orWhere('mobile', $fromNumber);
            })->first();

            if (! $person) {
                $person = Person::create([
                    'first_name' => 'Unknown',
                    'last_name' => '',
                    'phone' => $fromNumber,
                ]);
            }

            $conversation = Conversation::where('channel', ConversationChannelEnum::WHATSAPP)
                ->where('status', ConversationStatusEnum::OPEN)
                ->whereHas('participants', function ($q) use ($person) {
                    $q->where('participant_type', Person::class)
                        ->where('participant_id', $person->id);
                })
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'uuid' => (string) Str::uuid(),
                    'subject' => 'WhatsApp conversation with '.($person->first_name ?: $fromNumber),
                    'channel' => ConversationChannelEnum::WHATSAPP,
                    'status' => ConversationStatusEnum::OPEN,
                    'last_message_at' => now(),
                    'owner_id' => null,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => Person::class,
                    'participant_id' => $person->id,
                    'is_primary' => true,
                ]);

                $this->eventDispatcher->record($conversation, 'conversation.created', 'Conversation Created', $conversation->subject, [
                    'channel' => 'whatsapp',
                ]);
            }

            $data = [
                'tenant_id' => $account->tenant_id,
                'conversation_id' => $conversation->id,
                'person_id' => $person->id,
                'whatsapp_phone_number_id' => $phoneNumber->id,
                'provider_message_id' => $messageId,
                'type' => $messageType,
                'from_number' => $fromNumber,
                'to_number' => $toNumber,
                'content' => $content,
                'media_url' => $mediaUrl,
                'sent_at' => isset($messageData['timestamp']) ? date('Y-m-d H:i:s', $messageData['timestamp']) : now(),
            ];

            $this->messageService->persistInbound($data);

            $conversation->update(['last_message_at' => now()]);

            $this->storeWebhookLog($account, 'message_processed', [
                'message_id' => $messageId,
                'from' => $fromNumber,
                'conversation_id' => $conversation->id,
                'person_id' => $person->id,
            ]);
        });
    }

    private function processStatusUpdate(array $statusData, WhatsAppAccount $account): void
    {
        DB::transaction(function () use ($statusData, $account) {
            $providerMessageId = $statusData['id'] ?? '';

            $message = WhatsAppMessage::where('provider_message_id', $providerMessageId)->first();

            if (! $message) {
                return;
            }

            $status = $statusData['status'] ?? '';
            $timestamp = isset($statusData['timestamp']) ? date('Y-m-d H:i:s', $statusData['timestamp']) : null;

            $statusMap = [
                'sent' => WhatsAppMessageStatusEnum::SENT,
                'delivered' => WhatsAppMessageStatusEnum::DELIVERED,
                'read' => WhatsAppMessageStatusEnum::READ,
                'failed' => WhatsAppMessageStatusEnum::FAILED,
            ];

            if (isset($statusMap[$status])) {
                $timestampKey = $status.'_at';
                $this->messageService->updateStatus($message, $statusMap[$status], [
                    $timestampKey => $timestamp,
                ]);
            }

            $this->storeWebhookLog($account, 'status_updated', [
                'provider_message_id' => $providerMessageId,
                'status' => $status,
            ]);
        });
    }

    private function resolveMessageType(array $messageData): WhatsAppMessageTypeEnum
    {
        $type = $messageData['type'] ?? 'text';

        return match ($type) {
            'image' => WhatsAppMessageTypeEnum::IMAGE,
            'document' => WhatsAppMessageTypeEnum::DOCUMENT,
            'audio' => WhatsAppMessageTypeEnum::AUDIO,
            'video' => WhatsAppMessageTypeEnum::VIDEO,
            'sticker' => WhatsAppMessageTypeEnum::STICKER,
            'location' => WhatsAppMessageTypeEnum::LOCATION,
            'contacts' => WhatsAppMessageTypeEnum::CONTACT,
            default => WhatsAppMessageTypeEnum::TEXT,
        };
    }

    private function extractContent(array $messageData, WhatsAppMessageTypeEnum $type): ?string
    {
        return match ($type) {
            WhatsAppMessageTypeEnum::TEXT => $messageData['text']['body'] ?? null,
            WhatsAppMessageTypeEnum::IMAGE => $messageData['image']['caption'] ?? null,
            WhatsAppMessageTypeEnum::DOCUMENT => $messageData['document']['caption'] ?? null,
            WhatsAppMessageTypeEnum::LOCATION => isset($messageData['location'])
                ? ($messageData['location']['latitude'] ?? '').','.($messageData['location']['longitude'] ?? '')
                : null,
            WhatsAppMessageTypeEnum::CONTACT => isset($messageData['contacts'][0])
                ? ($messageData['contacts'][0]['name']['formatted_name'] ?? '')
                : null,
            default => null,
        };
    }

    private function extractMediaUrl(array $messageData, WhatsAppMessageTypeEnum $type): ?string
    {
        $mediaKey = match ($type) {
            WhatsAppMessageTypeEnum::IMAGE => 'image',
            WhatsAppMessageTypeEnum::DOCUMENT => 'document',
            WhatsAppMessageTypeEnum::AUDIO => 'audio',
            WhatsAppMessageTypeEnum::VIDEO => 'video',
            WhatsAppMessageTypeEnum::STICKER => 'sticker',
            default => null,
        };

        if ($mediaKey && isset($messageData[$mediaKey])) {
            return $messageData[$mediaKey]['link'] ?? $messageData[$mediaKey]['id'] ?? null;
        }

        return null;
    }

    private function storeWebhookLog(WhatsAppAccount $account, string $eventType, array $data): void
    {
        WhatsAppWebhookLog::create([
            'tenant_id' => $account->tenant_id,
            'whatsapp_account_id' => $account->id,
            'event_type' => $eventType,
            'payload' => $data,
        ]);
    }
}
