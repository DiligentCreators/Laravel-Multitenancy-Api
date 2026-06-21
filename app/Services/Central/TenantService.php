<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'company_name', 'name', 'username', 'email',
        'credit_balance', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Tenant $tenant,
        protected TenantProvisioningService $provisioningService,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->tenant
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = Tenant::search($search)->keys();
                $query->whereIn((new Tenant)->getQualifiedKeyName(), $ids);
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy($sort, $direction);
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
        return DB::transaction(function () use ($data) {
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
        });
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data) {
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
        });
    }
}
