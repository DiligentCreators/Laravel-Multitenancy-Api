<?php

namespace App\Services\Crm;

use App\Models\Crm\TaskComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaskCommentService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'task_id', 'parent_id', 'owner_id',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return TaskComment::query()
            ->with(['owner', 'replies'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(int $taskId, array $filters = [], int $perPage = 25)
    {
        $query = $this->query()->where('task_id', $taskId);

        if ($search = $filters['search'] ?? null) {
            $query->where('content', 'like', "%{$search}%");
        }

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        } else {
            $query->whereNull('parent_id');
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): TaskComment
    {
        return TaskComment::with(['owner', 'replies'])->findOrFail($id);
    }

    public function create(array $data): TaskComment
    {
        return TaskComment::create($data);
    }

    public function update(TaskComment $comment, array $data): TaskComment
    {
        $comment->update($data);

        return $comment;
    }

    public function delete(TaskComment $comment): void
    {
        $comment->delete();
    }

    public function getForTask(int $taskId): Collection
    {
        return TaskComment::where('task_id', $taskId)
            ->whereNull('parent_id')
            ->with('replies')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getReplies(int $parentId): Collection
    {
        return TaskComment::where('parent_id', $parentId)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
