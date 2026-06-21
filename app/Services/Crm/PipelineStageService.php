<?php

namespace App\Services\Crm;

use App\Models\Crm\PipelineStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PipelineStageService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'pipeline_id', 'name', 'sort_order', 'probability',
        'is_won_stage', 'is_lost_stage', 'color',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return PipelineStage::query()->with(['pipeline'])->orderBy('sort_order');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($pipelineId = $filters['pipeline_id'] ?? null) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($search = $filters['search'] ?? null) {
            $ids = PipelineStage::search($search)->keys();
            $query->whereIn((new PipelineStage)->getQualifiedKeyName(), $ids);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'sort_order';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): PipelineStage
    {
        return PipelineStage::with(['pipeline'])->findOrFail($id);
    }

    public function create(array $data): PipelineStage
    {
        return PipelineStage::create($data);
    }

    public function update(PipelineStage $stage, array $data): PipelineStage
    {
        $stage->update($data);

        return $stage;
    }

    public function delete(PipelineStage $stage): void
    {
        $stage->delete();
    }

    public function getByPipeline(int $pipelineId): Collection
    {
        return PipelineStage::where('pipeline_id', $pipelineId)->orderBy('sort_order')->get();
    }

    public function reorder(array $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order as $index => $id) {
                PipelineStage::where('id', $id)->update(['sort_order' => $index]);
            }
        });
    }
}
