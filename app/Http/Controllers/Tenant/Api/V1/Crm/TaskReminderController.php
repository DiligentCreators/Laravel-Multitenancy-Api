<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\TaskReminderResource;
use App\Models\Crm\TaskReminder;
use App\Services\ApiResponseService;
use App\Services\Crm\TaskReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskReminderController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TaskReminderService $taskReminderService,
    ) {
        parent::__construct($api);
    }

    public function index(int $taskId): JsonResponse
    {
        Gate::authorize('viewAny', TaskReminder::class);

        $perPage = min((int) request('per_page', 25), 100);
        $reminders = $this->taskReminderService->paginate($taskId, $perPage);

        return $this->api->success('Task reminders retrieved successfully', TaskReminderResource::collection($reminders));
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        Gate::authorize('create', TaskReminder::class);

        $data = $request->validate([
            'remind_at' => ['required', 'date'],
        ]);

        $data['task_id'] = $taskId;
        $data['owner_id'] = auth()->id();

        $reminder = $this->taskReminderService->create($data);

        return $this->api->success('Task reminder created successfully', new TaskReminderResource($reminder), 201);
    }

    public function show(TaskReminder $taskReminder): JsonResponse
    {
        Gate::authorize('view', $taskReminder);

        $taskReminder = $this->taskReminderService->find($taskReminder->id);

        return $this->api->success('Task reminder retrieved successfully', new TaskReminderResource($taskReminder));
    }

    public function update(Request $request, TaskReminder $taskReminder): JsonResponse
    {
        Gate::authorize('update', $taskReminder);

        $data = $request->validate([
            'remind_at' => ['required', 'date'],
        ]);

        $taskReminder = $this->taskReminderService->update($taskReminder, $data);

        return $this->api->success('Task reminder updated successfully', new TaskReminderResource($taskReminder));
    }

    public function destroy(TaskReminder $taskReminder): JsonResponse
    {
        Gate::authorize('delete', $taskReminder);

        $this->taskReminderService->delete($taskReminder);

        return $this->api->success('Task reminder deleted successfully');
    }
}
