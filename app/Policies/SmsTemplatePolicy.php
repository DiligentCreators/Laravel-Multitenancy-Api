<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\SmsTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmsTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('sms-templates.list');
    }

    public function view(CentralUser $centralUser, SmsTemplate $smsTemplate): bool
    {
        return $centralUser->can('sms-templates.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('sms-templates.create');
    }

    public function update(CentralUser $centralUser, SmsTemplate $smsTemplate): bool
    {
        return $centralUser->can('sms-templates.update');
    }

    public function delete(CentralUser $centralUser, SmsTemplate $smsTemplate): bool
    {
        return $centralUser->can('sms-templates.delete');
    }
}
