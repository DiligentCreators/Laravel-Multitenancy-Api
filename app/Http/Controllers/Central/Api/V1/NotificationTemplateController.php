<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\NotificationTemplate\StoreNotificationTemplateRequest;
use App\Http\Requests\Central\Api\V1\NotificationTemplate\UpdateNotificationTemplateRequest;
use App\Http\Resources\Central\Api\V1\NotificationTemplate\ListNotificationTemplateResource;
use App\Http\Resources\Central\Api\V1\NotificationTemplate\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\ApiResponseService;
use App\Services\Central\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NotificationTemplateController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly NotificationTemplateService $notificationTemplateService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', NotificationTemplate::class);

        $templates = $this->notificationTemplateService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'Notification templates retrieved successfully',
            ListNotificationTemplateResource::collection($templates),
        );
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', NotificationTemplate::class);

        $template = $this->notificationTemplateService->create($request->validated());

        return $this->api->success(
            'Notification template created successfully',
            new NotificationTemplateResource($template),
            201,
        );
    }

    public function show(NotificationTemplate $notificationTemplate): JsonResponse
    {
        Gate::authorize('view', $notificationTemplate);

        return $this->api->success(
            'Notification template retrieved successfully',
            new NotificationTemplateResource($notificationTemplate),
        );
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        Gate::authorize('update', $notificationTemplate);

        $this->notificationTemplateService->update($notificationTemplate, $request->validated());

        return $this->api->success(
            'Notification template updated successfully',
            new NotificationTemplateResource($notificationTemplate),
        );
    }

    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        Gate::authorize('delete', $notificationTemplate);

        $this->notificationTemplateService->delete($notificationTemplate);

        return $this->api->success(
            'Notification template deleted successfully',
            null,
        );
    }
}
