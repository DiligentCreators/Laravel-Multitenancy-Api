<?php

namespace App\Models;

use App\Observers\TenantObserver;
use App\Policies\TenantPolicy;
use Database\Factories\Central\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property-read string $name
 * @property-read string $email
 */
#[UsePolicy(TenantPolicy::class)]
#[ObservedBy(TenantObserver::class)]
class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasDomains, HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'company_name',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'company_name',
            'deleted_at',
        ];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /** @param Tenant $query */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    /** @return HasMany<User, $this> */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Domain, $this> */
    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
