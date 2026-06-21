<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\SettingGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SettingGroupService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected SettingGroup $settingGroup,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'sort_order'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'sort_order')
            : 'sort_order';

        $direction = in_array($request->input('direction', 'asc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'asc')
            : 'asc';

        return $this->settingGroup
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = SettingGroup::search($search)->keys();
                $query->whereIn((new SettingGroup)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sort, $direction);
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->with('settings')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->with('settings')->get();
    }

    public function find(int|string $id): SettingGroup
    {
        return $this->settingGroup->query()->with('settings')->findOrFail($id);
    }

    public function create(array $data): SettingGroup
    {
        return $this->settingGroup->create($data);
    }

    public function update(SettingGroup $settingGroup, array $data): SettingGroup
    {
        $settingGroup->update($data);

        return $settingGroup;
    }

    public function delete(SettingGroup $settingGroup): void
    {
        $settingGroup->delete();
    }
}
