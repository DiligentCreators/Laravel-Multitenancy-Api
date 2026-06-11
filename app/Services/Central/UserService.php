<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\CentralUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class UserService
{
    public function __construct(
        protected CentralUser $user,
    ) {}

    public static function protectedUsers(): array
    {
        return config('central-protected-users.protected', []);
    }

    public function query(Request $request): Builder
    {
        return $this->user
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', 'like', "%{$search}%");
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
            ->whereNotIn('id', self::protectedUsers())
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
    }

    public function paginate(Request $request, int $perPage = 15, ?int $excludeId = null): LengthAwarePaginator
    {
        return $this->query($request)
            ->when($excludeId !== null, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): CentralUser
    {
        return $this->user
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): CentralUser
    {
        $roles = $data['role'] ?? [];
        unset($data['role']);

        $user = $this->user->create($data);

        if (! empty($roles)) {
            $user->syncRoles($roles);
        }

        return $user;
    }

    public function update(CentralUser $user, array $data): CentralUser
    {
        $roles = $data['role'] ?? null;
        unset($data['role']);

        $user->update($data);

        if (is_array($roles)) {
            $user->syncRoles($roles);
        }

        return $user;
    }

    public function changePassword(CentralUser $user): void
    {
        $user->update([
            'password' => bcrypt($user->password),
        ]);
    }

    public function suspend(CentralUser $user): void
    {
        $user->update([
            'is_suspended' => true,
        ]);
    }

    public function unsuspend(CentralUser $user): void
    {
        $user->update([
            'is_suspended' => false,
        ]);
    }
}
