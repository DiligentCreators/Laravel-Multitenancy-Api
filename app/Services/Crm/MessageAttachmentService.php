<?php

namespace App\Services\Crm;

use App\Models\Crm\MessageAttachment;

class MessageAttachmentService
{
    public function create(int $messageId, array $data): MessageAttachment
    {
        $data['message_id'] = $messageId;

        return MessageAttachment::create($data);
    }

    public function delete(MessageAttachment $attachment): void
    {
        $attachment->delete();
    }
}
