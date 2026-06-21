<?php

namespace App\Enums;

enum WhatsAppMessageStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
}
