<?php

namespace App\Models;

use App\Notifications\Central\Auth\ResetPassword;
use Database\Factories\CentralUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/*
 * Central users manage the SaaS platform itself.
 *
 * They are NOT tenant members. They exist outside the tenant domain
 * and have no tenant_id. Their roles/permissions are scoped via
 * Spatie's guard_name = 'central-api'.
 *
 * Uses the 'central_users' table.
 * Authenticated via the 'central-api' Sanctum guard.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class CentralUser extends Authenticatable
{
    /** @use HasFactory<CentralUserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = 'central_users';

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
