<?php

namespace App\Services\Crm;

use App\Models\Crm\Source;
use Illuminate\Database\Eloquent\Builder;

class SourceService
{
    public function query(): Builder
    {
        return Source::query()->orderBy('name');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): Source
    {
        return Source::findOrFail($id);
    }

    public function create(array $data): Source
    {
        return Source::create($data);
    }

    public function update(Source $source, array $data): Source
    {
        $source->update($data);

        return $source;
    }

    public function delete(Source $source): void
    {
        $source->delete();
    }
}
