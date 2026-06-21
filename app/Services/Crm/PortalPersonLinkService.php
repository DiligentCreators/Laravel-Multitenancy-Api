<?php

namespace App\Services\Crm;

use App\Models\Crm\PortalPersonLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PortalPersonLinkService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return PortalPersonLink::with(['portalUser', 'person', 'organization'])
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $id): PortalPersonLink
    {
        return PortalPersonLink::with(['portalUser', 'person', 'organization'])
            ->findOrFail($id);
    }

    public function create(array $data): PortalPersonLink
    {
        return PortalPersonLink::create($data);
    }

    public function delete(PortalPersonLink $link): void
    {
        $link->delete();
    }
}
