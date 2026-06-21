<?php

namespace App\Enums;

enum WhatsAppMessageTypeEnum: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case DOCUMENT = 'document';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case STICKER = 'sticker';
    case LOCATION = 'location';
    case CONTACT = 'contact';
}
