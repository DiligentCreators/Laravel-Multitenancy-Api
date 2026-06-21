<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCalendarEventRequest;
use App\Http\Requests\Crm\UpdateCalendarEventRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\CalendarEventResource;
use App\Models\Crm\CalendarEvent;
use App\Services\ApiResponseService;
use App\Services\Crm\CalendarEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CalendarEventController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly CalendarEventService $calendarEventService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CalendarEvent::class);

        $perPage = min((int) request('per_page', 25), 100);
        $events = $this->calendarEventService->paginateWithFilters(request()->only([
            'search', 'status', 'owner_id', 'from_date', 'to_date', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Calendar events retrieved successfully', CalendarEventResource::collection($events));
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        Gate::authorize('create', CalendarEvent::class);

        $event = $this->calendarEventService->create($request->validated());

        return $this->api->success('Calendar event created successfully', new CalendarEventResource($event), 201);
    }

    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        Gate::authorize('view', $calendarEvent);

        $event = $this->calendarEventService->find($calendarEvent->id);

        return $this->api->success('Calendar event retrieved successfully', new CalendarEventResource($event));
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        Gate::authorize('update', $calendarEvent);

        $event = $this->calendarEventService->update($calendarEvent, $request->validated());

        return $this->api->success('Calendar event updated successfully', new CalendarEventResource($event));
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        Gate::authorize('delete', $calendarEvent);

        $this->calendarEventService->delete($calendarEvent);

        return $this->api->success('Calendar event deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', CalendarEvent::class);

        $this->calendarEventService->restore($id);

        return $this->api->success('Calendar event restored successfully');
    }
}
