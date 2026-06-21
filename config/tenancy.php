<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Tenant;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;
use Stancl\Tenancy\UUIDGenerator;

return [
    'tenant_model' => Tenant::class,
    'id_generator' => UUIDGenerator::class,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app.
     *
     * Only relevant if you're using the domain or subdomain identification middleware.
     */
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        parse_url(config('app.url'), PHP_URL_HOST),
    ],

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     *
     * NOTE: Single-database mode. DatabaseTenancyBootstrapper is intentionally
     * omitted because all tenants share one database. Tenant isolation is
     * achieved via tenant_id foreign keys and the TenantScope global scope,
     * not separate databases.
     *
     * Available bootstrappers:
     *   - CacheTenancyBootstrapper      Prefixes cache with tenant tag
     *   - FilesystemTenancyBootstrapper Suffixes storage paths with tenant
     *   - QueueTenancyBootstrapper      Prefixes queue names with tenant
     */
    'bootstrappers' => [
        CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     *
     * NOTE: Not used in single-database mode. Kept for reference in case
     * the application later migrates to per-tenant databases.
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        'template_tenant_connection' => null,

        'prefix' => 'tenant',
        'suffix' => '',

        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     */
    'cache' => [
        'tag_base' => 'tenant',
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     */
    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    /**
     * Features are classes that provide additional functionality
     * not needed for tenancy to be bootstrapped.
     */
    'features' => [
        UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\UniversalRoutes::class,
        // Stancl\Tenancy\Features\TenantConfig::class,
        // Stancl\Tenancy\Features\CrossDomainRedirect::class,
        // Stancl\Tenancy\Features\ViteBundler::class,
    ],

    /**
     * Should tenancy routes be registered.
     */
    'routes' => true,

    /**
     * Parameters used by the tenants:migrate command.
     *
     * NOTE: In single-database mode, all migrations are regular Laravel
     * migrations (php artisan migrate). The tenant/ directory can be
     * used for tenant-scoped migrations if needed in the future.
     */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
