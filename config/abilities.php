<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sanctum Token Abilities
|--------------------------------------------------------------------------
|
| Central and tenant token abilities are defined here to ensure
| consistent naming across the application. Each ability string
| represents a specific permission granted to a Sanctum token.
|
| Convention:
|   central:{resource}:{action}  — Central domain abilities
|   tenant:{resource}:{action}   — Tenant domain abilities
|
| Common actions: create, read, update, delete, manage
|
| Usage in route middleware:
|   ->middleware('abilities:central:tenants:manage')
|   ->middleware('ability:tenant:contacts:read,tenant:contacts:update')
|
| Usage when issuing tokens:
|   $user->createToken('token-name', ['central:tenants:manage']);
|   $user->createToken('token-name', ['tenant:contacts:read']);
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Central Abilities
    |--------------------------------------------------------------------------
    |
    | These abilities are checked on central API routes (auth:central-api).
    |
    */

    'central' => [
        'tenant' => [
            'create' => 'central:tenant:create',
            'read' => 'central:tenant:read',
            'update' => 'central:tenant:update',
            'delete' => 'central:tenant:delete',
            'manage' => 'central:tenant:manage',
        ],
        'billing' => [
            'read' => 'central:billing:read',
            'manage' => 'central:billing:manage',
        ],
        'subscription' => [
            'read' => 'central:subscription:read',
            'manage' => 'central:subscription:manage',
        ],
        'users' => [
            'manage' => 'central:users:manage',
        ],
        'settings' => [
            'read' => 'central:settings:read',
            'update' => 'central:settings:update',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Abilities
    |--------------------------------------------------------------------------
    |
    | These abilities are checked on tenant API routes (auth:tenant-api).
    |
    */

    'tenant' => [
        'contacts' => [
            'create' => 'tenant:contacts:create',
            'read' => 'tenant:contacts:read',
            'update' => 'tenant:contacts:update',
            'delete' => 'tenant:contacts:delete',
            'manage' => 'tenant:contacts:manage',
        ],
        'leads' => [
            'create' => 'tenant:leads:create',
            'read' => 'tenant:leads:read',
            'update' => 'tenant:leads:update',
            'delete' => 'tenant:leads:delete',
            'manage' => 'tenant:leads:manage',
        ],
        'deals' => [
            'create' => 'tenant:deals:create',
            'read' => 'tenant:deals:read',
            'update' => 'tenant:deals:update',
            'delete' => 'tenant:deals:delete',
            'manage' => 'tenant:deals:manage',
        ],
        'pipeline' => [
            'read' => 'tenant:pipeline:read',
            'manage' => 'tenant:pipeline:manage',
        ],
        'messages' => [
            'create' => 'tenant:messages:create',
            'read' => 'tenant:messages:read',
            'manage' => 'tenant:messages:manage',
        ],
        'reports' => [
            'read' => 'tenant:reports:read',
            'manage' => 'tenant:reports:manage',
        ],
        'users' => [
            'manage' => 'tenant:users:manage',
        ],
        'settings' => [
            'read' => 'tenant:settings:read',
            'update' => 'tenant:settings:update',
        ],
    ],

];
