<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Notifications\Portal\Auth\ResetPassword;
use App\Policies\Crm\PortalUserPolicy;
use Database\Factories\Crm\PortalUserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[UseFactory(PortalUserFactory::class)]
#[UsePolicy(PortalUserPolicy::class)]
class PortalUser extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'portal_users';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_active',
        'invited_at',
        'registered_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function personLinks(): HasMany
    {
        return $this->hasMany(PortalPersonLink::class, 'portal_user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }
}
