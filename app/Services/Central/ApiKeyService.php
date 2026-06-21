<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\ApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ApiKeyService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'last_used_at', 'expires_at', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected ApiKey $apiKey,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->apiKey
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = ApiKey::search($search)->keys();
                $query->whereIn((new ApiKey)->getQualifiedKeyName(), $ids);
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

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): ApiKey
    {
        return $this->apiKey
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): ApiKey
    {
        $data['key'] = ApiKey::generateKey();

        return $this->apiKey->create($data);
    }

    public function update(ApiKey $apiKey, array $data): ApiKey
    {
        $apiKey->update($data);

        return $apiKey;
    }

    public function regenerate(ApiKey $apiKey): string
    {
        $newKey = $apiKey->regenerate();

        return $newKey;
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->update(['expires_at' => now()]);
    }
}
