<?php

namespace App\Services\Crm;

use App\Actions\Crm\MoveLeadStageAction;
use App\Models\Crm\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'person_id', 'organization_id', 'source_id',
        'status_id', 'pipeline_id', 'pipeline_stage_id', 'title',
        'value', 'probability', 'expected_close_date', 'won_at', 'lost_at',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly MoveLeadStageAction $moveLeadStageAction,
    ) {}

    public function query(): Builder
    {
        return Lead::query()
            ->with(['status', 'source', 'owner', 'pipeline', 'pipelineStage', 'person', 'organization'])
            ->orderBy('created_at', 'desc');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Lead::search($search)->keys();
            $query->whereIn((new Lead)->getQualifiedKeyName(), $ids);
        }

        if ($statusId = $filters['status_id'] ?? null) {
            $query->where('status_id', $statusId);
        }

        if ($sourceId = $filters['source_id'] ?? null) {
            $query->where('source_id', $sourceId);
        }

        if ($ownerId = $filters['owner_id'] ?? null) {
            $query->where('owner_id', $ownerId);
        }

        if ($pipelineId = $filters['pipeline_id'] ?? null) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($pipelineStageId = $filters['pipeline_stage_id'] ?? null) {
            $query->where('pipeline_stage_id', $pipelineStageId);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Lead
    {
        return Lead::with(['status', 'source', 'owner', 'pipeline', 'pipelineStage', 'person', 'organization', 'tags'])
            ->findOrFail($id);
    }

    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $lead = Lead::create($data);

            if ($tagIds) {
                $lead->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($lead, 'lead.created', 'Lead Created', $lead->title, null, Auth::id());

            return $lead;
        });
    }

    public function update(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            $tagIds = $data['tag_ids'] ?? null;
            unset($data['tag_ids']);

            $lead->update($data);

            if ($tagIds !== null) {
                $lead->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($lead, 'lead.updated', 'Lead Updated', $lead->title, null, Auth::id());

            return $lead;
        });
    }

    public function delete(Lead $lead): void
    {
        DB::transaction(function () use ($lead) {
            $this->eventDispatcher->record($lead, 'lead.deleted', 'Lead Deleted', $lead->title, null, Auth::id());

            $lead->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            Lead::withTrashed()->findOrFail($id)->restore();

            $lead = Lead::withTrashed()->find($id);

            if ($lead) {
                $this->eventDispatcher->record($lead, 'lead.restored', 'Lead Restored', $lead->title, null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        Lead::withTrashed()->findOrFail($id)->forceDelete();
    }

    public function moveStage(Lead $lead, int $pipelineStageId, ?string $reason = null): Lead
    {
        $wasWon = $lead->won_at !== null;
        $wasLost = $lead->lost_at !== null;

        $lead = $this->moveLeadStageAction->execute($lead, $pipelineStageId, $reason);
        $lead->load('pipelineStage');

        $this->eventDispatcher->record($lead, 'lead.stage_moved', 'Lead Stage Moved', "{$lead->title} moved to {$lead->pipelineStage->name}", [
            'pipeline_stage_id' => $lead->pipeline_stage_id,
            'reason' => $reason,
        ], Auth::id());

        if ($lead->won_at !== null && ! $wasWon) {
            $this->eventDispatcher->record($lead, 'lead.won', 'Lead Won', $lead->title, null, Auth::id());
        }

        if ($lead->lost_at !== null && ! $wasLost) {
            $this->eventDispatcher->record($lead, 'lead.lost', 'Lead Lost', $lead->title, null, Auth::id());
        }

        return $lead;
    }
}
