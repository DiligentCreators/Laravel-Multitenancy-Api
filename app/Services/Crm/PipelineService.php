<?php

namespace App\Services\Crm;

use App\Models\Crm\Pipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PipelineService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'is_default', 'is_active', 'sort_order',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return Pipeline::query()->with(['stages'])->orderBy('sort_order');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Pipeline::search($search)->keys();
            $query->whereIn((new Pipeline)->getQualifiedKeyName(), $ids);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'sort_order';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Pipeline
    {
        return Pipeline::with(['stages'])->findOrFail($id);
    }

    public function create(array $data): Pipeline
    {
        return Pipeline::create($data);
    }

    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        $pipeline->update($data);

        return $pipeline;
    }

    public function delete(Pipeline $pipeline): void
    {
        $pipeline->delete();
    }

    public function getAllActive(): Collection
    {
        return Pipeline::where('is_active', true)->orderBy('sort_order')->get();
    }
}
