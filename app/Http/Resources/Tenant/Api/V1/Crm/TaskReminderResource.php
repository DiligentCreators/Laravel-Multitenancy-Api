<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\TaskReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskReminder */
class TaskReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'remind_at' => $this->remind_at,
            'notified_at' => $this->notified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
