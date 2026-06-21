<?php

namespace App\Services\Crm;

use App\Models\Crm\OrganizationPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OrganizationPersonService
{
    public function query(): Builder
    {
        return OrganizationPerson::query()->with(['organization', 'person'])->orderBy('created_at', 'desc');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): OrganizationPerson
    {
        return OrganizationPerson::with(['organization', 'person'])->findOrFail($id);
    }

    public function create(array $data): OrganizationPerson
    {
        $existing = OrganizationPerson::where('organization_id', $data['organization_id'])
            ->where('person_id', $data['person_id'])
            ->whereNull('end_date')
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'organization_id' => ['An active relationship already exists between this organization and person.'],
            ]);
        }

        $data['tenant_id'] ??= tenant()->id;

        return OrganizationPerson::create($data);
    }

    public function update(OrganizationPerson $op, array $data): OrganizationPerson
    {
        $op->update($data);

        return $op;
    }

    public function delete(OrganizationPerson $op): void
    {
        $op->delete();
    }

    public function getPeopleForOrganization(int $orgId): Collection
    {
        return OrganizationPerson::where('organization_id', $orgId)->with('person')->get();
    }

    public function getOrganizationsForPerson(int $personId): Collection
    {
        return OrganizationPerson::where('person_id', $personId)->with('organization')->get();
    }
}
