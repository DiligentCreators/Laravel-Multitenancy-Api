<?php

namespace App\Enums;

enum MessageStatusEnum: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
}
