<?php

namespace App\Enums\Central;

enum AdminAuditActionEnum: string
{
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case IMPERSONATE_START = 'impersonate_start';
    case IMPERSONATE_STOP = 'impersonate_stop';
    case CONFIG_CHANGE = 'config_change';
    case PERMISSION_CHANGE = 'permission_change';
    case SYSTEM_SETTING_CHANGE = 'system_setting_change';
    case DATA_EXPORT = 'data_export';
    case DATA_DELETE = 'data_delete';
}
