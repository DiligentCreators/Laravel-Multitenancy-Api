<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationTemplateService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'channel', 'title',
        'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected NotificationTemplate $notificationTemplate,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->notificationTemplate
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = NotificationTemplate::search($search)->keys();
                $query->whereIn((new NotificationTemplate)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('channel'), fn (Builder $query) => $query->where('channel', $request->input('channel')))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sort, $direction);
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)->paginate($perPage)->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): NotificationTemplate
    {
        return $this->notificationTemplate->query()->findOrFail($id);
    }

    public function create(array $data): NotificationTemplate
    {
        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->notificationTemplate->create($data);
    }

    public function update(NotificationTemplate $notificationTemplate, array $data): NotificationTemplate
    {
        $notificationTemplate->update($data);

        return $notificationTemplate;
    }

    public function delete(NotificationTemplate $notificationTemplate): void
    {
        $notificationTemplate->delete();
    }
}
