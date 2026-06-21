<?php

namespace App\Services\Crm;

use App\Models\Crm\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'status_id', 'source_id',
        'name', 'website', 'email', 'phone',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Organization::query()
            ->with(['status', 'source', 'owner'])
            ->orderBy('name');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = Organization::search($search)->keys();
            $query->whereIn((new Organization)->getQualifiedKeyName(), $ids);
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

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'name';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Organization
    {
        return Organization::with(['status', 'source', 'owner', 'tags', 'people'])->findOrFail($id);
    }

    public function create(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $org = Organization::create($data);

            if (! empty($tagIds)) {
                $org->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($org, 'organization.created', 'Organization Created', $org->name, null, Auth::id());

            return $org;
        });
    }

    public function update(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data) {
            $tagIds = $data['tag_ids'] ?? null;
            unset($data['tag_ids']);

            $organization->update($data);

            if ($tagIds !== null) {
                $organization->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($organization, 'organization.updated', 'Organization Updated', $organization->name, null, Auth::id());

            return $organization;
        });
    }

    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            $this->eventDispatcher->record($organization, 'organization.deleted', 'Organization Deleted', $organization->name, null, Auth::id());

            $organization->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            Organization::withTrashed()->findOrFail($id)->restore();

            $organization = Organization::withTrashed()->find($id);

            if ($organization) {
                $this->eventDispatcher->record($organization, 'organization.restored', 'Organization Restored', $organization->name, null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        Organization::withTrashed()->findOrFail($id)->forceDelete();
    }
}
