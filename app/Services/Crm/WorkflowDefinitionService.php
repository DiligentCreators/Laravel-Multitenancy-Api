<?php

namespace App\Services\Crm;

use App\Models\Crm\WorkflowDefinition;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkflowDefinitionService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return WorkflowDefinition::orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): WorkflowDefinition
    {
        return WorkflowDefinition::findOrFail($id);
    }

    public function create(array $data): WorkflowDefinition
    {
        return WorkflowDefinition::create($data);
    }

    public function update(WorkflowDefinition $workflow, array $data): WorkflowDefinition
    {
        $workflow->update($data);

        return $workflow;
    }

    public function delete(WorkflowDefinition $workflow): void
    {
        $workflow->delete();
    }
}
