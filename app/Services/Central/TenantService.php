<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TenantService
{
    public function __construct(
        protected Tenant $tenant,
        protected TenantProvisioningService $provisioningService,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->tenant
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('users', function (Builder $query) use ($search) {
                            $query->whereAny([
                                'name',
                                'username',
                                'email',
                            ], 'like', "%{$search}%");
                        })
                        ->orWhereHas('domains', function (Builder $query) use ($search) {
                            $query->where('domain', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
    }

    public function paginate(
        Request $request,
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->query($request)
            ->with(['users' => fn ($q) => $q->withTrashed(), 'domains' => fn ($q) => $q->withTrashed()])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request)
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Tenant
    {
        return $this->tenant
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function findOrFail(int|string $id): Tenant
    {
        return $this->tenant
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Tenant
    {
        $tenant = $this->tenant->create($data);

        $tenant->domains()->create([
            'domain' => $data['domain'],
        ]);

        $this->provisioningService->provision($tenant, [
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        return $tenant;
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        $domain = $tenant->load('domains');

        $domain->domains()->first()->update([
            'domain' => $data['domain'],
        ]);

        $user = $tenant->users()->first();

        $user->update([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        return $tenant;
    }
}
