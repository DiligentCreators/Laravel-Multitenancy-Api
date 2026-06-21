<?php

namespace App\Services\Crm;

use App\Actions\Crm\RecurringEventAction;
use App\Models\Crm\CalendarEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CalendarEventService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'title', 'starts_at', 'ends_at',
        'all_day', 'status', 'location', 'color',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly RecurringEventAction $recurringEventAction,
    ) {}

    public function query(): Builder
    {
        return CalendarEvent::query()
            ->with(['owner'])
            ->orderBy('starts_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = CalendarEvent::search($search)->keys();
            $query->whereIn((new CalendarEvent)->getQualifiedKeyName(), $ids);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        if ($fromDate = $filters['from_date'] ?? null) {
            $query->where('starts_at', '>=', $fromDate);
        }

        if ($toDate = $filters['to_date'] ?? null) {
            $query->where('starts_at', '<=', $toDate);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'starts_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): CalendarEvent
    {
        return CalendarEvent::with(['owner', 'recurringPattern'])
            ->findOrFail($id);
    }

    public function create(array $data): CalendarEvent
    {
        return DB::transaction(function () use ($data) {
            $recurringData = $data['recurring'] ?? null;
            unset($data['recurring']);

            $event = CalendarEvent::create($data);

            if ($recurringData) {
                $this->recurringEventAction->generate($event, $recurringData);
            }

            $this->eventDispatcher->record($event, 'calendar.created', 'Calendar Event Created', $event->title, [
                'starts_at' => $event->starts_at,
            ], Auth::id());

            return $event;
        });
    }

    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data) {
            $event->update($data);

            $this->eventDispatcher->record($event, 'calendar.updated', 'Calendar Event Updated', $event->title, null, Auth::id());

            return $event;
        });
    }

    public function delete(CalendarEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $this->eventDispatcher->record($event, 'calendar.deleted', 'Calendar Event Deleted', $event->title, null, Auth::id());

            $event->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            CalendarEvent::withTrashed()->findOrFail($id)->restore();

            $event = CalendarEvent::withTrashed()->find($id);

            if ($event) {
                $this->eventDispatcher->record($event, 'calendar.restored', 'Calendar Event Restored', $event->title, null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        CalendarEvent::withTrashed()->findOrFail($id)->forceDelete();
    }
}
