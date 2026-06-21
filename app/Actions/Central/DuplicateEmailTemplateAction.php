<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Models\EmailTemplate;
use App\Services\Central\EmailTemplateService;

class DuplicateEmailTemplateAction
{
    public function __construct(
        protected EmailTemplateService $emailTemplateService,
    ) {}

    public function execute(EmailTemplate $emailTemplate): EmailTemplate
    {
        return $this->emailTemplateService->duplicate($emailTemplate);
    }
}
