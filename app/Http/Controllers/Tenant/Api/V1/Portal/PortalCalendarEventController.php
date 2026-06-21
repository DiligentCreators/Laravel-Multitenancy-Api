<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\CalendarEvent;
use App\Models\Crm\Person;
use App\Models\Crm\PortalUser;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalCalendarEventController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $perPage = min((int) $request->get('per_page', 25), 100);

        $events = CalendarEvent::where('eventable_type', (new Person)->getMorphClass())
            ->whereIn('eventable_id', $personIds)
            ->orderBy('starts_at', 'desc')
            ->paginate($perPage);

        return $this->api->success('Calendar events retrieved successfully', $events);
    }

    public function show(CalendarEvent $calendarEvent, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $hasAccess = $calendarEvent->eventable_type === (new Person)->getMorphClass()
            && $personIds->contains($calendarEvent->eventable_id);

        if (! $hasAccess) {
            return $this->api->error('Calendar event not found.', 404);
        }

        return $this->api->success('Calendar event retrieved successfully', $calendarEvent);
    }
}
