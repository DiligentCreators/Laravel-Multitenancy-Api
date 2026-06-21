<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\TaskCommentResource;
use App\Models\Crm\TaskComment;
use App\Services\ApiResponseService;
use App\Services\Crm\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TaskCommentService $taskCommentService,
    ) {
        parent::__construct($api);
    }

    public function index(int $taskId): JsonResponse
    {
        Gate::authorize('viewAny', TaskComment::class);

        $perPage = min((int) request('per_page', 25), 100);
        $comments = $this->taskCommentService->paginateWithFilters($taskId, request()->only([
            'search', 'parent_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Task comments retrieved successfully', TaskCommentResource::collection($comments));
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        Gate::authorize('create', TaskComment::class);

        $data = $request->validate([
            'content' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:crm_task_comments,id'],
        ]);

        $data['task_id'] = $taskId;
        $data['owner_id'] = auth()->id();

        $comment = $this->taskCommentService->create($data);

        return $this->api->success('Task comment created successfully', new TaskCommentResource($comment), 201);
    }

    public function show(TaskComment $taskComment): JsonResponse
    {
        Gate::authorize('view', $taskComment);

        $taskComment = $this->taskCommentService->find($taskComment->id);

        return $this->api->success('Task comment retrieved successfully', new TaskCommentResource($taskComment));
    }

    public function update(Request $request, TaskComment $taskComment): JsonResponse
    {
        Gate::authorize('update', $taskComment);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $taskComment = $this->taskCommentService->update($taskComment, $data);

        return $this->api->success('Task comment updated successfully', new TaskCommentResource($taskComment));
    }

    public function destroy(TaskComment $taskComment): JsonResponse
    {
        Gate::authorize('delete', $taskComment);

        $this->taskCommentService->delete($taskComment);

        return $this->api->success('Task comment deleted successfully');
    }
}
