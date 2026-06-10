<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/*
 * Tenant users belong to a tenant and are scoped by tenant_id.
 *
 * Each tenant has its own set of users (owners, managers, agents, employees).
 * All queries are automatically filtered by the current tenant via
 * the BelongsToTenant trait.
 *
 * Uses the 'users' table.
 * Authenticated via the 'tenant-api' Sanctum guard.
 * Roles/permissions use Spatie with guard_name = 'tenant-api'.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'username', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Route notifications to the tenant-specific mail channel.
     */
    public function routeNotificationForMail(Notification $notification): array
    {
        return [$this->email => $this->name];
    }
}
