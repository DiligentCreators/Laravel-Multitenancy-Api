<?php

namespace App\Http\Controllers\Tenant\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Crm\Person;
use App\Models\Crm\PortalUser;
use App\Models\Crm\Task;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalTaskController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $perPage = min((int) $request->get('per_page', 25), 100);

        $tasks = Task::where('taskable_type', (new Person)->getMorphClass())
            ->whereIn('taskable_id', $personIds)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->api->success('Tasks retrieved successfully', $tasks);
    }

    public function show(Task $task, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $user = $request->user();
        $personIds = $user->personLinks()->whereNotNull('person_id')->pluck('person_id');

        $hasAccess = $task->taskable_type === (new Person)->getMorphClass()
            && $personIds->contains($task->taskable_id);

        if (! $hasAccess) {
            return $this->api->error('Task not found.', 404);
        }

        return $this->api->success('Task retrieved successfully', $task);
    }
}
