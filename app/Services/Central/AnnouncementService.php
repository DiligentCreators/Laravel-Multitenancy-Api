<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AnnouncementService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'title', 'type', 'starts_at', 'ends_at',
        'is_active', 'audience_type', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Announcement $announcement,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->announcement
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Announcement::search($search)->keys();
                $query->whereIn((new Announcement)->getQualifiedKeyName(), $ids);
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

    public function find(int|string $id): Announcement
    {
        return $this->announcement
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Announcement
    {
        if (isset($data['audience_ids']) && is_array($data['audience_ids'])) {
            $data['audience_ids'] = json_encode($data['audience_ids']);
        }

        return $this->announcement->create($data);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        if (isset($data['audience_ids']) && is_array($data['audience_ids'])) {
            $data['audience_ids'] = json_encode($data['audience_ids']);
        }

        $announcement->update($data);

        return $announcement;
    }

    public function active(): Collection
    {
        return $this->announcement->query()->active()->get();
    }
}
