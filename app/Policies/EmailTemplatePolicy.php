<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\EmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(CentralUser $centralUser): bool
    {
        return $centralUser->can('email-templates.list');
    }

    public function view(CentralUser $centralUser, EmailTemplate $emailTemplate): bool
    {
        return $centralUser->can('email-templates.read');
    }

    public function create(CentralUser $centralUser): bool
    {
        return $centralUser->can('email-templates.create');
    }

    public function update(CentralUser $centralUser, EmailTemplate $emailTemplate): bool
    {
        return $centralUser->can('email-templates.update');
    }

    public function delete(CentralUser $centralUser, EmailTemplate $emailTemplate): bool
    {
        return $centralUser->can('email-templates.delete');
    }
}
