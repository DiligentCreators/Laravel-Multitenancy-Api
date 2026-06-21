<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'group_id', 'key', 'label', 'type',
        'is_public', 'is_encrypted', 'sort_order', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Setting $setting,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'sort_order'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'sort_order')
            : 'sort_order';

        $direction = in_array($request->input('direction', 'asc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'asc')
            : 'asc';

        return $this->setting
            ->query()
            ->with('group')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = Setting::search($search)->keys();
                $query->whereIn((new Setting)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('group_id'), fn (Builder $query) => $query->where('group_id', $request->input('group_id')))
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

    public function find(int|string $id): Setting
    {
        $setting = $this->setting->query()->with('group')->findOrFail($id);

        if ($setting->is_encrypted && $setting->value) {
            $setting->value = Crypt::decryptString($setting->value);
        }

        return $setting;
    }

    public function create(array $data): Setting
    {
        if (isset($data['is_encrypted']) && $data['is_encrypted'] && ! empty($data['value'])) {
            $data['value'] = Crypt::encryptString($data['value']);
        }

        return $this->setting->create($data);
    }

    public function update(Setting $setting, array $data): Setting
    {
        if (isset($data['is_encrypted']) && $data['is_encrypted'] && isset($data['value'])) {
            $data['value'] = Crypt::encryptString($data['value']);
        }

        $setting->update($data);

        if ($setting->is_encrypted && $setting->value) {
            $setting->value = Crypt::decryptString($setting->value);
        }

        return $setting;
    }

    public function delete(Setting $setting): void
    {
        $setting->delete();
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $setting = $this->setting->query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        $value = $setting->value ?? $setting->default_value;

        if ($setting->is_encrypted && $value) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception) {
                return $default;
            }
        }

        return match ($setting->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (float) $value : $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function getGroupedSettings(): Collection
    {
        return $this->setting
            ->query()
            ->with('group')
            ->whereHas('group', fn (Builder $query) => $query->where('is_active', true))
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();
    }
}
