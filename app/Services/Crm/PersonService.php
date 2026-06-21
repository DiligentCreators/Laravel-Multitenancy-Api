<?php

namespace App\Services\Crm;

use App\Models\Crm\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'owner_id', 'status_id', 'source_id',
        'first_name', 'last_name', 'email', 'phone', 'mobile',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return Person::query()->with(['status', 'source', 'owner'])->orderBy('first_name');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'first_name';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        return $this->query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereIn((new Person)->getQualifiedKeyName(), Person::search($search)->keys()))
            ->when($filters['status_id'] ?? null, fn ($q, $statusId) => $q->where('status_id', $statusId))
            ->when($filters['source_id'] ?? null, fn ($q, $sourceId) => $q->where('source_id', $sourceId))
            ->when($filters['owner_id'] ?? null, fn ($q, $ownerId) => $q->where('owner_id', $ownerId))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    public function find(int $id): Person
    {
        return Person::with(['status', 'source', 'owner', 'tags'])->findOrFail($id);
    }

    public function create(array $data): Person
    {
        return DB::transaction(function () use ($data) {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $person = Person::create($data);

            if ($tagIds) {
                $person->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($person, 'person.created', 'Person Created', "{$person->first_name} {$person->last_name}", null, Auth::id());

            return $person;
        });
    }

    public function update(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            $tagIds = $data['tag_ids'] ?? null;
            unset($data['tag_ids']);

            $person->update($data);

            if ($tagIds !== null) {
                $person->tags()->sync($tagIds);
            }

            $this->eventDispatcher->record($person, 'person.updated', 'Person Updated', "{$person->first_name} {$person->last_name}", null, Auth::id());

            return $person;
        });
    }

    public function delete(Person $person): void
    {
        DB::transaction(function () use ($person) {
            $this->eventDispatcher->record($person, 'person.deleted', 'Person Deleted', "{$person->first_name} {$person->last_name}", null, Auth::id());

            $person->delete();
        });
    }

    public function restore(int $id): void
    {
        DB::transaction(function () use ($id) {
            Person::withTrashed()->findOrFail($id)->restore();

            $person = Person::withTrashed()->find($id);

            if ($person) {
                $this->eventDispatcher->record($person, 'person.restored', 'Person Restored', "{$person->first_name} {$person->last_name}", null, Auth::id());
            }
        });
    }

    public function forceDelete(int $id): void
    {
        Person::withTrashed()->findOrFail($id)->forceDelete();
    }
}
