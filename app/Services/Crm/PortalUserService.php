<?php

namespace App\Services\Crm;

use App\Models\Crm\PortalUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class PortalUserService
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return PortalUser::query()->orderBy('name');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): PortalUser
    {
        return PortalUser::findOrFail($id);
    }

    public function create(array $data): PortalUser
    {
        return DB::transaction(function () use ($data) {
            $portalUser = PortalUser::create($data);

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.created',
                "Portal user {$portalUser->name} was created",
            );

            return $portalUser;
        });
    }

    public function update(PortalUser $portalUser, array $data): PortalUser
    {
        return DB::transaction(function () use ($portalUser, $data) {
            $portalUser->update($data);

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.updated',
                "Portal user {$portalUser->name} was updated",
            );

            return $portalUser;
        });
    }

    public function delete(PortalUser $portalUser): void
    {
        DB::transaction(function () use ($portalUser) {
            $portalUser->delete();

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.deleted',
                "Portal user {$portalUser->name} was deleted",
            );
        });
    }

    public function restore(PortalUser $portalUser): void
    {
        DB::transaction(function () use ($portalUser) {
            $portalUser->restore();

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.restored',
                "Portal user {$portalUser->name} was restored",
            );
        });
    }

    public function invite(PortalUser $portalUser): void
    {
        DB::transaction(function () use ($portalUser) {
            $portalUser->update(['invited_at' => now()]);

            Password::broker('portal_users')->sendResetLink(
                ['email' => $portalUser->email]
            );

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.invited',
                "Portal user {$portalUser->name} was invited",
            );
        });
    }

    public function activate(PortalUser $portalUser): void
    {
        DB::transaction(function () use ($portalUser) {
            $portalUser->update(['is_active' => true]);

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.activated',
                "Portal user {$portalUser->name} was activated",
            );
        });
    }

    public function deactivate(PortalUser $portalUser): void
    {
        DB::transaction(function () use ($portalUser) {
            $portalUser->update(['is_active' => false]);

            $this->eventDispatcher->record(
                $portalUser,
                'portal.user.deactivated',
                "Portal user {$portalUser->name} was deactivated",
            );
        });
    }
}
