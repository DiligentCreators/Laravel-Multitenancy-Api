<?php

namespace App\Services\Crm;

use App\Models\Crm\TimelineEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TimelineService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'entity_type', 'entity_id', 'event_type',
        'title', 'caused_by', 'occurred_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return TimelineEntry::query()->with(['entity', 'causer'])->orderBy('occurred_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($entityType = $filters['entity_type'] ?? null) {
            $query->where('entity_type', $entityType);
        }

        if ($entityId = $filters['entity_id'] ?? null) {
            $query->where('entity_id', $entityId);
        }

        if ($eventType = $filters['event_type'] ?? null) {
            $query->where('event_type', $eventType);
        }

        if ($search = $filters['search'] ?? null) {
            $ids = TimelineEntry::search($search)->keys();
            $query->whereIn((new TimelineEntry)->getQualifiedKeyName(), $ids);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'occurred_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): TimelineEntry
    {
        return TimelineEntry::with(['entity', 'causer'])->findOrFail($id);
    }

    public function getForEntity(string $entityType, int $entityId): Collection
    {
        return TimelineEntry::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('occurred_at', 'desc')
            ->get();
    }

    public function getForEntityPaginated(string $entityType, int $entityId, int $perPage = 25): LengthAwarePaginator
    {
        return TimelineEntry::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('occurred_at', 'desc')
            ->paginate(min($perPage, 100));
    }
}
