<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreTaskRequest;
use App\Http\Requests\Crm\UpdateTaskRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\TaskResource;
use App\Models\Crm\Task;
use App\Services\ApiResponseService;
use App\Services\Crm\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TaskService $taskService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $perPage = min((int) request('per_page', 25), 100);
        $tasks = $this->taskService->paginateWithFilters(request()->only([
            'search', 'status_id', 'priority', 'owner_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Tasks retrieved successfully', TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $task = $this->taskService->create($request->validated());

        return $this->api->success('Task created successfully', new TaskResource($task), 201);
    }

    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $task = $this->taskService->find($task->id);

        return $this->api->success('Task retrieved successfully', new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $task = $this->taskService->update($task, $request->validated());

        return $this->api->success('Task updated successfully', new TaskResource($task));
    }

    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $this->taskService->delete($task);

        return $this->api->success('Task deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $this->taskService->restore($id);

        return $this->api->success('Task restored successfully');
    }
}
