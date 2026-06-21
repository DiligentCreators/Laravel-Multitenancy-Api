<?php

namespace App\Services\Crm;

use App\Enums\Central\NotificationChannelEnum;
use App\Models\Crm\TaskReminder;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;

class TaskReminderService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function query(): Builder
    {
        return TaskReminder::query()->orderBy('created_at', 'desc');
    }

    public function paginate(int $taskId, int $perPage = 25)
    {
        return $this->query()
            ->where('task_id', $taskId)
            ->paginate($perPage);
    }

    public function find(int $id): TaskReminder
    {
        return TaskReminder::findOrFail($id);
    }

    public function create(array $data): TaskReminder
    {
        $reminder = TaskReminder::create($data);

        $this->notificationService->queue(
            $reminder->owner_id,
            'Task Reminder',
            "Reminder for task #{$reminder->task_id}",
            NotificationChannelEnum::IN_APP,
            ['task_reminder_id' => $reminder->id],
        );

        return $reminder;
    }

    public function update(TaskReminder $reminder, array $data): TaskReminder
    {
        $reminder->update($data);

        return $reminder;
    }

    public function delete(TaskReminder $reminder): void
    {
        $reminder->delete();
    }
}
