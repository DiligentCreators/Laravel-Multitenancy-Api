<?php

namespace App\Enums;

enum ConversationChannelEnum: string
{
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case EMAIL = 'email';
    case INTERNAL = 'internal';
}
