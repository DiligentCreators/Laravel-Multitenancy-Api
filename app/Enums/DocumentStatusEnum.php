<?php

namespace App\Enums;

enum DocumentStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
