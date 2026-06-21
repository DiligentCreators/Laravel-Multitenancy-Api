<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Feature;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class FeatureService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'type', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Feature $feature,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->feature
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Feature::search($search)->keys();
                $query->whereIn((new Feature)->getQualifiedKeyName(), $ids);
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

    public function find(int|string $id): Feature
    {
        return $this->feature
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Feature
    {
        return $this->feature->create($data);
    }

    public function update(Feature $feature, array $data): Feature
    {
        $feature->update($data);

        return $feature;
    }

    public function isActive(Feature $feature): Feature
    {
        $feature->update([
            'is_active' => true,
        ]);

        return $feature;
    }

    public function isInactive(Feature $feature): Feature
    {
        $feature->update([
            'is_active' => false,
        ]);

        return $feature;
    }
}
