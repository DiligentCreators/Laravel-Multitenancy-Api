<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Central\Api\V1\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\Central\Api\V1\Announcement\AnnouncementResource;
use App\Http\Resources\Central\Api\V1\Announcement\ListAnnouncementResource;
use App\Models\Announcement;
use App\Services\ApiResponseService;
use App\Services\Central\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly AnnouncementService $announcementService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $announcements = $this->announcementService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'announcements retrieved successfully',
            ListAnnouncementResource::collection($announcements),
        );
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        $announcement = $this->announcementService->create($request->validated());

        return $this->api->success(
            'Announcement has been created successfully',
            new AnnouncementResource($announcement),
            201,
        );
    }

    public function show(Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        if ($announcement->trashed()) {
            return $this->api->notFound('Announcement has been deleted.');
        }

        return $this->api->success(
            'Announcement retrieved successfully',
            new AnnouncementResource($announcement),
        );
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);

        if ($announcement->trashed()) {
            return $this->api->notFound('Cannot update a deleted announcement.');
        }

        $this->announcementService->update($announcement, $request->validated());

        return $this->api->success(
            'Announcement has been updated successfully',
            new AnnouncementResource($announcement),
        );
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        Gate::authorize('delete', $announcement);

        if ($announcement->trashed()) {
            return $this->api->notFound('Announcement is already deleted.');
        }

        $announcement->delete();

        return $this->api->success(
            'Announcement has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(Announcement $announcement): JsonResponse
    {
        Gate::authorize('restore', $announcement);

        if (! $announcement->trashed()) {
            return $this->api->notFound('Announcement is not deleted.');
        }

        $announcement->restore();

        return $this->api->success(
            'Announcement has been restored successfully',
            new AnnouncementResource($announcement),
        );
    }

    public function forceDelete(Announcement $announcement): JsonResponse
    {
        Gate::authorize('forceDelete', $announcement);

        if (! $announcement->trashed()) {
            return $this->api->error('Announcement must be deleted before force deleting.', 400);
        }

        $announcement->forceDelete();

        return $this->api->success(
            'Announcement has been force deleted successfully',
            null,
            200,
        );
    }
}
