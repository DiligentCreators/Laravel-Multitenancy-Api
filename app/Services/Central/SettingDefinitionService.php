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
    public function __construct(
        protected SettingDefinition $settingDefinition,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->settingDefinition
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
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
