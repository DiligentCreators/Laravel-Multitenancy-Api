<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Actions\Central\DuplicateEmailTemplateAction;
use App\Actions\Central\PreviewEmailTemplateAction;
use App\Actions\Central\SendTestEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\EmailTemplate\PreviewEmailTemplateRequest;
use App\Http\Requests\Central\Api\V1\EmailTemplate\SendTestEmailRequest;
use App\Http\Requests\Central\Api\V1\EmailTemplate\StoreEmailTemplateRequest;
use App\Http\Requests\Central\Api\V1\EmailTemplate\UpdateEmailTemplateRequest;
use App\Http\Resources\Central\Api\V1\EmailTemplate\EmailTemplateResource;
use App\Http\Resources\Central\Api\V1\EmailTemplate\ListEmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\ApiResponseService;
use App\Services\Central\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmailTemplateController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly EmailTemplateService $emailTemplateService,
        private readonly PreviewEmailTemplateAction $previewAction,
        private readonly SendTestEmailAction $sendTestAction,
        private readonly DuplicateEmailTemplateAction $duplicateAction,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = $this->emailTemplateService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'Email templates retrieved successfully',
            ListEmailTemplateResource::collection($templates),
        );
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', EmailTemplate::class);

        $template = $this->emailTemplateService->create($request->validated());

        return $this->api->success(
            'Email template created successfully',
            new EmailTemplateResource($template),
            201,
        );
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $emailTemplate->loadCount('versions');

        return $this->api->success(
            'Email template retrieved successfully',
            new EmailTemplateResource($emailTemplate),
        );
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('update', $emailTemplate);

        $this->emailTemplateService->update($emailTemplate, $request->validated());

        return $this->api->success(
            'Email template updated successfully',
            new EmailTemplateResource($emailTemplate->fresh()),
        );
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('delete', $emailTemplate);

        $this->emailTemplateService->delete($emailTemplate);

        return $this->api->success(
            'Email template deleted successfully',
            null,
        );
    }

    public function preview(EmailTemplate $emailTemplate, PreviewEmailTemplateRequest $request): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $preview = $this->previewAction->execute(
            $emailTemplate,
            $request->input('variables', []),
        );

        return $this->api->success(
            'Email template preview generated',
            $preview,
        );
    }

    public function sendTest(EmailTemplate $emailTemplate, SendTestEmailRequest $request): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $this->sendTestAction->execute(
            $emailTemplate,
            $request->input('recipient'),
            $request->input('variables', []),
        );

        return $this->api->success(
            'Test email sent successfully',
        );
    }

    public function duplicate(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('create', EmailTemplate::class);

        $duplicate = $this->duplicateAction->execute($emailTemplate);

        return $this->api->success(
            'Email template duplicated successfully',
            new EmailTemplateResource($duplicate),
            201,
        );
    }

    public function versions(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $versions = $this->emailTemplateService->getVersions($emailTemplate);

        return $this->api->success(
            'Email template versions retrieved successfully',
            $versions,
        );
    }
}
