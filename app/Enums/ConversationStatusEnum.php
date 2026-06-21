<?php

namespace App\Enums;

enum ConversationStatusEnum: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';
}
