<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Models\EmailTemplate;
use App\Services\Central\EmailTemplateService;

class SendTestEmailAction
{
    public function __construct(
        protected EmailTemplateService $emailTemplateService,
    ) {}

    public function execute(EmailTemplate $emailTemplate, string $recipient, array $variables = []): void
    {
        $this->emailTemplateService->sendTest($emailTemplate, $recipient, $variables);
    }
}
