<?php

namespace App\Services\Crm;

use App\Models\Crm\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'title', 'status_id', 'priority',
        'due_at', 'completed_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Task::query()
            ->with(['status', 'owner'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Task::search($search)->keys();
            $query->whereIn((new Task)->getQualifiedKeyName(), $ids);
        }

        if ($statusId = $filters['status_id'] ?? null) {
            $query->where('status_id', $statusId);
        }

        if ($priority = $filters['priority'] ?? null) {
            $query->where('priority', $priority);
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

    public function find(int $id): Task
    {
        return Task::with(['status', 'owner', 'comments', 'reminders'])
            ->findOrFail($id);
    }

    public function create(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $task = Task::create($data);

            $this->eventDispatcher->record($task, 'task.created', 'Task Created', $task->title, [
                'priority' => $task->priority?->value,
                'due_at' => $task->due_at,
            ], Auth::id());

            return $task;
        });
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $wasCompleted = $task->completed_at !== null;

            $task->update($data);

            $task->refresh();

            if (! $wasCompleted && $task->completed_at !== null) {
                $this->eventDispatcher->record($task, 'task.completed', 'Task Completed', $task->title, null, Auth::id());
            } else {
                $this->eventDispatcher->record($task, 'task.updated', 'Task Updated', $task->title, null, Auth::id());
            }

            return $task;
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $this->eventDispatcher->record($task, 'task.deleted', 'Task Deleted', $task->title, null, Auth::id());

            $task->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            Task::withTrashed()->findOrFail($id)->restore();

            $task = Task::withTrashed()->find($id);

            if ($task) {
                $this->eventDispatcher->record($task, 'task.restored', 'Task Restored', $task->title, null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        Task::withTrashed()->findOrFail($id)->forceDelete();
    }
}
