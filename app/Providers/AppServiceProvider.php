<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Gate::before(function ($user, $ability) {
        //     if ($user->hasAnyRole(config('roles.bypass_permissions'))) {
        //         return true;
        //     }

        //     return null; // fallback to normal policies
        // });

        /**
         * In many cases, Laravel can automatically eager load the relationships you access.
         * To enable automatic eager loading,
         * you should invoke the Model::automaticallyEagerLoadRelationships method
         * within the boot method of your application's AppServiceProvider:
         *
         * Beta feature
         *
         * @link https://laravel.com/docs/12.x/eloquent-relationships#automatic-eager-loading
         */
        Model::automaticallyEagerLoadRelationships();

        // Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
