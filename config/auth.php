<?php

use App\Models\CentralUser;
use App\Models\Crm\PortalUser;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Three guards are defined:
    |
    |   web         — Session-based, for admin panel / Filament (if used)
    |   central-api — Sanctum-based, for central platform APIs
    |   tenant-api  — Sanctum-based, for tenant-scoped APIs
    |
    | Central-api and tenant-api use different providers so their user
    | models and tables are completely isolated. A central token can
    | never authenticate a tenant user, and vice versa.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        /*
         * Central API guard — authenticates CentralUser (central_users table).
         * Used on central routes: Route::middleware('auth:central-api')
         */
        'central-api' => [
            'driver' => 'sanctum',
            'provider' => 'central_users',
        ],

        /*
         * Tenant API guard — authenticates User (users table, tenant-scoped).
         * Used on tenant routes: Route::middleware('auth:tenant-api')
         */
        'tenant-api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        /*
         * Portal API guard — authenticates PortalUser (portal_users table, tenant-scoped).
         * Used on portal routes: Route::middleware('auth:portal-api')
         */
        'portal-api' => [
            'driver' => 'sanctum',
            'provider' => 'portal_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Two providers separate central platform users from tenant users.
    | They use different models and different database tables.
    |
    |   central_users → App\Models\CentralUser → central_users table
    |   users         → App\Models\User         → users table
    |
    */

    'providers' => [
        'central_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL_CENTRAL', CentralUser::class),
        ],

        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'portal_users' => [
            'driver' => 'eloquent',
            'model' => PortalUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'central_users' => [
            'provider' => 'central_users',
            'table' => env('CENTRAL_PASSWORD_RESET_TOKEN_TABLE', 'central_password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'portal_users' => [
            'provider' => 'portal_users',
            'table' => 'portal_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
