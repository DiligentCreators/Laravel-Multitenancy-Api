<?php

namespace App\Services\Crm;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\Crm\Task;
use App\Models\Crm\WorkflowDefinition;
use App\Models\Crm\WorkflowLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(
        private readonly FeatureGateService $featureGate,
    ) {}

    public function trigger(string $event, Model $entity): void
    {
        $tenant = tenant();

        if (! $tenant) {
            return;
        }

        $entityType = strtolower(class_basename($entity));
        $entityKey = $entity->getKey();
        $entityClass = get_class($entity);

        $workflows = WorkflowDefinition::where('tenant_id', $tenant->id)
            ->where('entity_type', $entityType)
            ->where('trigger_event', $event)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if (! $this->evaluateConditions($workflow, $entity)) {
                continue;
            }

            dispatch(new ExecuteWorkflowJob($tenant->id, $workflow->id, $entityClass, $entityKey));
        }
    }

    public function execute(WorkflowDefinition $workflow, Model $entity): void
    {
        if ($entity->tenant_id !== $workflow->tenant_id) {
            throw new \RuntimeException('Cross-tenant workflow execution denied');
        }

        DB::transaction(function () use ($workflow, $entity) {
            $log = WorkflowLog::create([
                'tenant_id' => $workflow->tenant_id,
                'workflow_id' => $workflow->id,
                'trigger_event' => $workflow->trigger_event,
                'triggerable_type' => $entity->getMorphClass(),
                'triggerable_id' => $entity->getKey(),
                'status' => 'pending',
            ]);

            try {
                $results = [];

                $entity::withoutEvents(function () use ($workflow, $entity, &$results) {
                    foreach ($workflow->actions as $action) {
                        $result = $this->executeAction($action, $entity);
                        $results[] = $result;
                    }
                });

                $log->update([
                    'status' => 'completed',
                    'result' => $results,
                ]);
            } catch (\Throwable $e) {
                $log->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function evaluateConditions(WorkflowDefinition $workflow, Model $entity): bool
    {
        $conditions = $workflow->conditions;

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $field => $expected) {
            $actual = $entity->{$field} ?? null;

            if ($actual != $expected) {
                return false;
            }
        }

        return true;
    }

    private function executeAction(array $action, Model $entity): array
    {
        return match ($action['type']) {
            'assign_owner' => $this->executeAssignOwner($action, $entity),
            'update_field' => $this->executeUpdateField($action, $entity),
            'create_task' => $this->executeCreateTask($action, $entity),
            'send_notification' => $this->executeSendNotification($action, $entity),
            default => ['action' => $action['type'], 'status' => 'unknown_type'],
        };
    }

    private function executeAssignOwner(array $action, Model $entity): array
    {
        $entity->owner_id = $action['user_id'];
        $entity->save();

        return ['action' => 'assign_owner', 'status' => 'completed', 'user_id' => $action['user_id']];
    }

    private function executeUpdateField(array $action, Model $entity): array
    {
        $entity->{$action['field']} = $action['value'];
        $entity->save();

        return ['action' => 'update_field', 'status' => 'completed', 'field' => $action['field']];
    }

    private function executeCreateTask(array $action, Model $entity): array
    {
        $task = Task::create([
            'tenant_id' => $entity->tenant_id,
            'taskable_type' => $entity->getMorphClass(),
            'taskable_id' => $entity->getKey(),
            'title' => $action['title'],
            'description' => $action['description'] ?? null,
            'owner_id' => $action['assigned_to'] ?? $entity->owner_id,
            'due_at' => $action['due_in_days'] ? now()->addDays($action['due_in_days']) : null,
        ]);

        return ['action' => 'create_task', 'status' => 'completed', 'task_id' => $task->id];
    }

    private function executeSendNotification(array $action, Model $entity): array
    {
        $userIds = $action['user_ids'] ?? [$entity->owner_id];

        foreach ($userIds as $userId) {
            $entity->notifyAction(
                $action['title'] ?? 'Workflow Notification',
                $action['body'] ?? '',
                $userId
            );
        }

        return ['action' => 'send_notification', 'status' => 'completed', 'recipients' => count($userIds)];
    }

    public function paginateLogs(int $workflowId, int $perPage = 25)
    {
        return WorkflowLog::where('workflow_id', $workflowId)
            ->latest()
            ->paginate($perPage);
    }
}
