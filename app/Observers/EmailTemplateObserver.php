<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\EmailTemplate;
use App\Services\Central\EmailTemplateService;

class EmailTemplateObserver
{
    public function __construct(
        protected EmailTemplateService $emailTemplateService,
    ) {}

    public function updated(EmailTemplate $emailTemplate): void
    {
        if ($emailTemplate->wasChanged(['subject', 'body'])) {
            $this->emailTemplateService->createVersion($emailTemplate);
        }
    }
}
