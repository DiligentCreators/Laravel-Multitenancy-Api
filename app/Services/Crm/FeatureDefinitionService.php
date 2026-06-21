<?php

namespace App\Services\Crm;

use App\Models\Crm\FeatureDefinition;
use Illuminate\Database\Eloquent\Builder;

class FeatureDefinitionService
{
    public function query(): Builder
    {
        return FeatureDefinition::orderBy('key');
    }

    public function paginate(int $perPage = 100)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): FeatureDefinition
    {
        return FeatureDefinition::findOrFail($id);
    }

    public function findByKey(string $key): ?FeatureDefinition
    {
        return FeatureDefinition::where('key', $key)->first();
    }
}
