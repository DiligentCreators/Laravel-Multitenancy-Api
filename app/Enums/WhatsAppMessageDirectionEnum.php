<?php

namespace App\Enums;

enum WhatsAppMessageDirectionEnum: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
