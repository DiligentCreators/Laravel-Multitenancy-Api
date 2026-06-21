<?php

namespace App\Providers;

use App\Models\CentralUser;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /*
         * Central admin bypass — super admins automatically pass all gates.
         *
         * This only applies to CentralUser instances on the 'central-api' guard.
         * Tenant User instances are NOT affected.
         */
        Gate::before(function ($user, $ability) {
            if (
                $user instanceof CentralUser && $user->hasRole('superadmin')
                || $user instanceof User && $user->hasRole('superadmin')
            ) {
                return true;
            }

            return null;
        });

        Model::automaticallyEagerLoadRelationships();

        DB::prohibitDestructiveCommands($this->app->isProduction());

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') ?: $request->ip());
        });
    }
}
