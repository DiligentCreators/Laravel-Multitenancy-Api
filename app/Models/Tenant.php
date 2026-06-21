<?php

namespace App\Models;

use App\Observers\TenantObserver;
use App\Policies\TenantPolicy;
use Carbon\Carbon;
use Database\Factories\Central\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
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
    use HasDomains, HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'id',
        'company_name',
        'name',
        'username',
        'email',
        'password',
        'data',
        'credit_balance',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'company_name',
            'name',
            'username',
            'email',
            'password',
            'deleted_at',
            'data',
            'credit_balance',
            'stripe_id',
            'stripe_customer_email',
            'card_brand',
            'card_last_four',
            'trial_ends_at',
            'stripe_account_id',
            'stripe_account_enabled',
        ];
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->data = $data;
        $this->save();
    }

    public function getBrandingConfig(): array
    {
        return $this->getSetting('branding', [
            'logo_url' => null,
            'primary_color' => null,
            'favicon_url' => null,
        ]);
    }

    public function getLocalizationConfig(): array
    {
        return $this->getSetting('localization', [
            'locale' => config('app.locale', 'en'),
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'Y-m-d',
        ]);
    }

    public function getNotificationConfig(): array
    {
        return $this->getSetting('notifications', [
            'email' => true,
            'in_app' => true,
        ]);
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

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($query) {
                $query->where('ends_at', '>=', Carbon::now())
                    ->orWhereNull('ends_at');
            })
            ->latest('id');
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->latest('id')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function activePlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
        ];
    }
}
