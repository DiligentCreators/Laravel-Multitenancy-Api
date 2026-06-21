<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'type', 'subject', 'starts_at',
        'ends_at', 'completed_at', 'status', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Activity::query()->with(['activityable'])->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Activity::search($search)->keys();
            $query->whereIn((new Activity)->getQualifiedKeyName(), $ids);
        }

        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Activity
    {
        return Activity::with(['activityable'])->findOrFail($id);
    }

    public function create(array $data): Activity
    {
        return DB::transaction(function () use ($data) {
            $activity = Activity::create($data);

            $this->eventDispatcher->record($activity, 'activity.created', 'Activity Created', "Activity: {$activity->subject}", [
                'activity_type' => $activity->type,
                'activity_id' => $activity->id,
            ], Auth::id());

            return $activity;
        });
    }

    public function update(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            $activity->update($data);

            $this->eventDispatcher->record($activity, 'activity.updated', 'Activity Updated', "Activity: {$activity->subject}", null, Auth::id());

            return $activity;
        });
    }

    public function delete(Activity $activity): void
    {
        $activity->delete();
    }

    public function restore(int $id): void
    {
        Activity::withTrashed()->findOrFail($id)->restore();
    }

    public function getForEntity(string $type, int $id): Collection
    {
        return Activity::where('activityable_type', $type)
            ->where('activityable_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForEntityPaginated(string $type, int $id, int $perPage = 25): LengthAwarePaginator
    {
        return Activity::where('activityable_type', $type)
            ->where('activityable_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100));
    }
}
