<?php

namespace App\Services\Crm;

use App\Models\Crm\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommentService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'parent_id', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Comment::query()->with(['commentable', 'replies'])->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Comment::search($search)->keys();
            $query->whereIn((new Comment)->getQualifiedKeyName(), $ids);
        }

        if ($parentId = $filters['parent_id'] ?? null) {
            $query->where('parent_id', $parentId);
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

    public function find(int $id): Comment
    {
        return Comment::with(['commentable', 'replies'])->findOrFail($id);
    }

    public function create(array $data): Comment
    {
        return DB::transaction(function () use ($data) {
            $comment = Comment::create($data);

            $this->eventDispatcher->record($comment, 'comment.created', 'Comment Added', null, [
                'comment_id' => $comment->id,
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'parent_id' => $comment->parent_id,
            ], Auth::id());

            return $comment;
        });
    }

    public function update(Comment $comment, array $data): Comment
    {
        $comment->update($data);

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function restore(int $id): void
    {
        Comment::withTrashed()->findOrFail($id)->restore();
    }

    public function getForEntity(string $type, int $id): Collection
    {
        return Comment::where('commentable_type', $type)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->with('replies')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForEntityPaginated(string $type, int $id, int $perPage = 25): LengthAwarePaginator
    {
        return Comment::where('commentable_type', $type)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->with('replies')
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100));
    }

    public function getReplies(int $parentId): Collection
    {
        return Comment::where('parent_id', $parentId)->orderBy('created_at', 'asc')->get();
    }
}
