<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCommentRequest;
use App\Http\Requests\Crm\UpdateCommentRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\CommentResource;
use App\Models\Crm\Comment;
use App\Services\ApiResponseService;
use App\Services\Crm\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly CommentService $commentService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Comment::class);

        $perPage = min((int) request('per_page', 25), 100);
        $comments = $this->commentService->paginateWithFilters(request()->only([
            'search', 'parent_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Comments retrieved successfully', CommentResource::collection($comments));
    }

    public function store(StoreCommentRequest $request): JsonResponse
    {
        Gate::authorize('create', Comment::class);

        $comment = $this->commentService->create($request->validated());

        return $this->api->success('Comment created successfully', new CommentResource($comment), 201);
    }

    public function show(Comment $comment): JsonResponse
    {
        Gate::authorize('view', $comment);

        $comment = $this->commentService->find($comment->id);

        return $this->api->success('Comment retrieved successfully', new CommentResource($comment));
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);

        $comment = $this->commentService->update($comment, $request->validated());

        return $this->api->success('Comment updated successfully', new CommentResource($comment));
    }

    public function destroy(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $this->commentService->delete($comment);

        return $this->api->success('Comment deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Comment::class);

        $this->commentService->restore($id);

        return $this->api->success('Comment restored successfully');
    }

    public function byEntity(string $type, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Comment::class);

        $perPage = min((int) request('per_page', 25), 100);
        $comments = $this->commentService->getForEntityPaginated($type, $id, $perPage);

        return $this->api->success('Comments retrieved successfully', CommentResource::collection($comments));
    }

    public function replies(int $parentId): JsonResponse
    {
        Gate::authorize('viewAny', Comment::class);

        $replies = $this->commentService->getReplies($parentId);

        return $this->api->success('Replies retrieved successfully', CommentResource::collection($replies));
    }
}
