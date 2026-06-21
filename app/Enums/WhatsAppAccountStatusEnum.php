<?php

namespace App\Enums;

enum WhatsAppAccountStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONNECTED = 'disconnected';
}
