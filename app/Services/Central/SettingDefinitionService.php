<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\SettingDefinition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SettingDefinitionService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'group', 'key', 'label', 'type',
        'is_required', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected SettingDefinition $settingDefinition,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->settingDefinition
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = SettingDefinition::search($search)->keys();
                $query->whereIn((new SettingDefinition)->getQualifiedKeyName(), $ids);
            })
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

    public function find(int|string $id): SettingDefinition
    {
        return $this->settingDefinition
            ->query()
            ->findOrFail($id);
    }

    public function create(array $data): SettingDefinition
    {
        return $this->settingDefinition->create($data);
    }

    public function update(SettingDefinition $settingDefinition, array $data): SettingDefinition
    {
        $settingDefinition->update($data);

        return $settingDefinition;
    }
}
