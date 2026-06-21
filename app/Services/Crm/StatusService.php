<?php

namespace App\Services\Crm;

use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StatusService
{
    public function query(): Builder
    {
        return Status::query()
            ->with('type')
            ->orderBy('order');
    }

    public function paginateWithFilters(?string $entityType = null, ?int $typeId = null, int $perPage = 25)
    {
        $query = $this->query();

        if ($entityType) {
            $query->whereHas('type', fn ($q) => $q->where('entity_type', $entityType));
        }

        if ($typeId) {
            $query->where('type_id', $typeId);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): Status
    {
        return Status::findOrFail($id);
    }

    public function findWithType(int $id): Status
    {
        return Status::with('type')->findOrFail($id);
    }

    public function create(array $data): Status
    {
        return DB::transaction(function () use ($data) {
            $maxOrder = Status::where('type_id', $data['type_id'])->max('order');

            $status = Status::create(array_merge($data, [
                'order' => $data['order'] ?? $maxOrder + 1,
            ]));

            if ($status->is_default) {
                Status::where('type_id', $status->type_id)
                    ->where('id', '!=', $status->id)
                    ->update(['is_default' => false]);
            }

            return $status;
        });
    }

    public function update(Status $status, array $data): Status
    {
        return DB::transaction(function () use ($status, $data) {
            $wasDefault = $status->is_default;

            $status->update($data);

            if (($data['is_default'] ?? false) && ! $wasDefault) {
                Status::where('type_id', $status->type_id)
                    ->where('id', '!=', $status->id)
                    ->update(['is_default' => false]);
            }

            return $status;
        });
    }

    public function delete(Status $status): void
    {
        $status->delete();
    }

    /** Helpers */
    public function getDefaultStatus(string $entityType): ?Status
    {
        $type = StatusType::where('entity_type', $entityType)->first();

        if (! $type) {
            return null;
        }

        return Status::where('type_id', $type->id)->where('is_default', true)->first();
    }

    public function getStatusesForEntity(string $entityType)
    {
        return Status::whereHas('type', fn ($q) => $q->where('entity_type', $entityType))
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    public function getStatusTypes(): Builder
    {
        return StatusType::query()->with('statuses');
    }

    public function createType(array $data): StatusType
    {
        return StatusType::create($data);
    }

    public function updateType(StatusType $type, array $data): StatusType
    {
        $type->update($data);

        return $type;
    }

    public function deleteType(StatusType $type): void
    {
        DB::transaction(function () use ($type) {
            $type->statuses()->delete();
            $type->delete();
        });
    }
}
