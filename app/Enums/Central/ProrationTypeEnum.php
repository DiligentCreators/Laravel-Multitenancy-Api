<?php

namespace App\Enums\Central;

enum ProrationTypeEnum: string
{
    case UPGRADE = 'upgrade';
    case DOWNGRADE = 'downgrade';
    case ADDON = 'addon';
    case REMOVE_ADDON = 'remove_addon';
    case CANCEL = 'cancel';
    case REACTIVATE = 'reactivate';
}
