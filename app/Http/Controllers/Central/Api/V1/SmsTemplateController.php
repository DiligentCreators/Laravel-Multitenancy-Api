<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\SmsTemplate\StoreSmsTemplateRequest;
use App\Http\Requests\Central\Api\V1\SmsTemplate\UpdateSmsTemplateRequest;
use App\Http\Resources\Central\Api\V1\SmsTemplate\ListSmsTemplateResource;
use App\Http\Resources\Central\Api\V1\SmsTemplate\SmsTemplateResource;
use App\Models\SmsTemplate;
use App\Services\ApiResponseService;
use App\Services\Central\SmsTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SmsTemplateController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SmsTemplateService $smsTemplateService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', SmsTemplate::class);

        $templates = $this->smsTemplateService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'SMS templates retrieved successfully',
            ListSmsTemplateResource::collection($templates),
        );
    }

    public function store(StoreSmsTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', SmsTemplate::class);

        $template = $this->smsTemplateService->create($request->validated());

        return $this->api->success(
            'SMS template created successfully',
            new SmsTemplateResource($template),
            201,
        );
    }

    public function show(SmsTemplate $smsTemplate): JsonResponse
    {
        Gate::authorize('view', $smsTemplate);

        return $this->api->success(
            'SMS template retrieved successfully',
            new SmsTemplateResource($smsTemplate),
        );
    }

    public function update(UpdateSmsTemplateRequest $request, SmsTemplate $smsTemplate): JsonResponse
    {
        Gate::authorize('update', $smsTemplate);

        $this->smsTemplateService->update($smsTemplate, $request->validated());

        return $this->api->success(
            'SMS template updated successfully',
            new SmsTemplateResource($smsTemplate),
        );
    }

    public function destroy(SmsTemplate $smsTemplate): JsonResponse
    {
        Gate::authorize('delete', $smsTemplate);

        $this->smsTemplateService->delete($smsTemplate);

        return $this->api->success(
            'SMS template deleted successfully',
            null,
        );
    }
}
