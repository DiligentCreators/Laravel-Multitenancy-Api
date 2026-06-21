<?php

namespace App\Enums;

enum MessageDirectionEnum: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
