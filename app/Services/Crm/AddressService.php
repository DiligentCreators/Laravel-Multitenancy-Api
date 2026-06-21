<?php

namespace App\Services\Crm;

use App\Models\Crm\Address;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    public function query(): Builder
    {
        return Address::query()->orderBy('created_at', 'desc');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): Address
    {
        return Address::findOrFail($id);
    }

    public function create(array $data): Address
    {
        return Address::create($data);
    }

    public function update(Address $addr, array $data): Address
    {
        $addr->update($data);

        return $addr;
    }

    public function delete(Address $addr): void
    {
        $addr->delete();
    }

    public function getForEntity(string $type, int $id): Collection
    {
        return Address::where('addressable_type', $type)->where('addressable_id', $id)->get();
    }

    public function getForEntityPaginated(string $type, int $id, int $perPage = 25): LengthAwarePaginator
    {
        return Address::where('addressable_type', $type)
            ->where('addressable_id', $id)
            ->paginate(min($perPage, 100));
    }

    public function getByType(string $type, int $id, string $addressType): Collection
    {
        return Address::where('addressable_type', $type)
            ->where('addressable_id', $id)
            ->where('type', $addressType)
            ->get();
    }
}
