# Laravel Multitenancy API

A production-ready **Laravel Multitenancy API** platform designed for SaaS applications with centralized management, tenant isolation, authentication, roles and permissions, and scalable architecture.

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)](https://laravel.com)
[![Tests](https://github.com/DiligentCreators/Laravel-Multitenancy-Api/actions/workflows/laravel.yml/badge.svg)](https://github.com/DiligentCreators/Laravel-Multitenancy-Api/actions/workflows/laravel.yml)
[![Quality](https://github.com/DiligentCreators/Laravel-Multitenancy-Api/actions/workflows/quality-gate.yml/badge.svg)](https://github.com/DiligentCreators/Laravel-Multitenancy-Api/actions/workflows/quality-gate.yml)
[![License](https://img.shields.io/github/license/DiligentCreators/Laravel-Multitenancy-Api)](LICENSE)

---

## Quick Start (5 Minutes)

```bash
# 1. Clone and install
git clone https://github.com/DiligentCreators/Laravel-Multitenancy-Api.git
cd Laravel-Multitenancy-Api
composer install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env, then migrate and seed
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\Central\\CentralDatabaseSeeder

# 4. Start the server
php artisan serve

# 5. Login as superadmin
# POST /api/central/v1/auth/login
# {"email": "superadmin@example.com", "password": "password"}
```

---

## Project Status

| Area                            | Status |
| ------------------------------- | ------ |
| Auth                            | ✅     |
| Tenant CRUD                     | ✅     |
| Roles & Permissions             | ✅     |
| Central Users                   | ✅     |
| Plans                           | ⬜     |
| Features                        | ⬜     |
| Plan Features                   | ⬜     |
| Subscriptions                   | ⬜     |
| Subscription Enforcement        | ⬜     |
| Tenant Users                    | ⬜     |
| Tenant Invitations              | ⬜     |
| Activity Logs                   | ⬜     |
| Notifications                   | ⬜     |
| Billing Gateway                 | ⬜     |
| Coupons                         | ⬜     |
| Invoices                        | ⬜     |
| Audit Logs                      | ⬜     |
| Settings                        | ⬜     |

---

## Features

- **Multi-Tenant Architecture** — Single-database tenant isolation with global scopes
- **Central Administration** — Manage tenants, users, roles, and permissions from a central hub
- **Authentication** — Sanctum token-based auth with separate guards for central and tenant contexts
- **Role & Permission Management** — Spatie Laravel Permission with guard-based isolation
- **API Versioning** — Namespaced API structure (`v1`) ready for future versions
- **Code Generation** — `dev:resource` command scaffolds full CRUD resources from stubs
- **Service Layer** — Business logic encapsulated in dedicated service classes
- **Request Validation** — All input validated via Form Request classes
- **Authorization** — Policies for fine-grained access control
- **Protected Resources** — Configurable protected users and roles
- **Soft Deletes** — Safe deletion with restore capability
- **Queue Support** — Database-backed queue for async jobs
- **Telescope** — Debugging and monitoring in local/development environments
- **CI/CD** — Automated testing, static analysis, and code style checks
- **Postman Collection** — Pre-configured API documentation and testing

---

## Technology Stack

| Layer            | Technology                                             |
| ---------------- | ------------------------------------------------------ |
| Backend          | Laravel 13                                             |
| Language         | PHP ^8.3                                               |
| Database         | MySQL 8+ / MariaDB / PostgreSQL / SQLite               |
| Authentication   | Laravel Sanctum                                        |
| Permissions      | Spatie Laravel Permission ^8.0                         |
| Multitenancy     | Stancl Tenancy ^3.10                                   |
| Queue            | Database (configurable: Redis, SQS, etc.)              |
| Cache            | Database (configurable: Redis, File, Memcached, etc.)  |
| Testing          | Pest PHP ^4.7                                          |
| Static Analysis  | Larastan ^3.10 (Level 5)                               |
| Code Style       | Laravel Pint ^1.27                                     |
| Debugging        | Laravel Telescope ^5.20                                |
| Monitoring       | Laravel Pail ^1.2                                      |

---

## Architecture Overview

The platform is divided into two distinct contexts:

### Central Application

The central application is the administrative hub of the SaaS platform. It operates outside any tenant context and manages:

- **Central Users** — Platform administrators who manage the entire system
- **Tenants** — Tenant organizations (each tenant represents a customer)
- **Roles & Permissions** — Central-level authorization managed via `guard_name = 'central-api'`
- **Plans & Subscriptions** (upcoming) — Billing and plan management

All central API routes are prefixed with `/api/central/v1/` and protected by the `central-api` Sanctum guard.

### Tenant Application

Each tenant operates within its own isolated context:

- **Tenant Users** — Users belonging to a specific tenant organization
- **Tenant Data** — All data scoped by `tenant_id` using global scopes
- **Roles & Permissions** — Tenant-level authorization managed via `guard_name = 'tenant-api'`

All tenant API routes are prefixed with `/api/tenant/v1/` and protected by the `tenant-api` Sanctum guard.

### Isolation Strategy

```
Central User
    ↓
Creates Tenant  ───→  Central Domain (api/central/v1/*)
    ↓
Tenant Domain (api/tenant/v1/*)
    ↓
Tenant Users with Tenant-level Roles
```

The platform uses **single-database isolation** — all tenants share one database. Data isolation is achieved through:

1. **`tenant_id` foreign keys** on all tenant-scoped tables
2. **`TenantScope`** — A global Eloquent scope that automatically filters queries by `tenant_id`
3. **`BelongsToTenant` trait** — Automatically assigns `tenant_id` on record creation

For role and permission isolation, Spatie's `guard_name` column separates central permissions (`central-api`) from tenant permissions (`tenant-api`).

---

## Project Structure

```
app/
├── Console/
│   └── Commands/              # Artisan commands (dev:resource, etc.)
│   └── Commands/DevResource/  # Code generation engine: generators, contracts, context
├── Enums/                     # Enum definitions (RoleScopeEnum, etc.)
├── Http/
│   ├── Controllers/
│   │   ├── Central/Api/V1/    # Central API controllers (thin, delegate to services)
│   │   └── Tenant/Api/V1/     # Tenant API controllers
│   ├── Middleware/             # Custom middleware (EnsureCentralDomain, InitializeTenancy)
│   ├── Requests/              # Form Request validation per action
│   │   ├── Central/Api/V1/    # Central request validators
│   │   └── Tenant/Api/V1/     # Tenant request validators
│   └── Resources/             # API Resource classes (JSON response shaping)
│       ├── Central/Api/V1/    # Central API resources
│       └── Tenant/Api/V1/     # Tenant API resources
├── Models/
│   ├── Central/               # Central-scoped model aliases (Role, Permission)
│   ├── Tenant/                # Tenant-scoped model aliases (Role, Permission)
│   │── Traits/                # Reusable model traits (BelongsToTenant)
│   └── ...                    # Base models (CentralUser, User, Tenant, Role, Permission, Domain)
├── Observers/                 # Model lifecycle hooks (CentralUserObserver, RoleObserver, TenantObserver)
├── Policies/                  # Authorization policies per model (CentralUserPolicy, RolePolicy, TenantPolicy)
├── Providers/                 # Service providers (AppServiceProvider, TenancyServiceProvider, etc.)
├── Rules/                     # Custom validation rules (PasswordRule)
├── Services/                  # Business logic layer
│   └── Central/               # Central services (UserService, RoleService, TenantService, TenantProvisioningService)
└── Traits/                    # Reusable controller traits (PaginatesRequestTrait)

bootstrap/app.php              # App config: middleware aliases, exception handling, SPA stateful domains

config/
├── abilities.php              # Sanctum token ability definitions
├── central-permissions.php    # Central resource → actions mapping
├── tenant-permissions.php     # Tenant resource → actions mapping
├── central-protected-users.php# User IDs excluded from listing/modification
├── central-protected-roles.php# Role names excluded from listing/modification
├── dev-resource.php           # Code generation defaults and configuration
└── tenancy.php                # Stancl tenancy configuration

database/
├── factories/                 # Model factories for testing/seeding
│   └── Central/               # Central factories
├── migrations/                # Database migrations
│   └── tenant/                # Tenant-scoped migrations (future use)
└── seeders/                   # Database seeders
    ├── Central/               # Central seeders (users, permissions, roles, assignments)
    └── Tenant/                # Tenant seeders (permissions, roles)

routes/
├── api.php                    # Route entry point (loads central route group)
├── central/v1.php             # Central API v1 route definitions
├── tenant.php                 # Tenant route loader (loaded by TenancyServiceProvider)
├── tenant/v1.php              # Tenant API v1 route definitions
└── console.php                # Console command schedule

tests/
├── Pest.php                   # Test suite configuration
├── Feature/Central/Auth/      # Feature tests (LoginTest, etc.)
├── Unit/                      # Unit tests
└── ...                        # Add tests here following the same pattern

.docs/postman/                 # Postman API collections and environments
.github/workflows/             # CI/CD pipeline workflows
stubs/dev-resource/            # Code generation stub templates (16 files)
```

### Directory Key

| Directory | Purpose |
| --------- | ------- |
| `app/Console/Commands/` | Artisan commands, including the `dev:resource` scaffold generator |
| `app/Enums/` | PHP enums for domain constants (e.g., `RoleScopeEnum`) |
| `app/Http/Controllers/` | Thin controllers — gate authorization, delegate to services, return resources |
| `app/Http/Middleware/` | Request middleware — tenant identification, domain validation |
| `app/Http/Requests/` | Form Request classes — one per controller action, contains all validation rules |
| `app/Http/Resources/` | API Resource classes — shape JSON responses, one per model per context |
| `app/Models/` | Eloquent models with traits, casts, relationships, and factory references |
| `app/Observers/` | Model lifecycle observers — hook into creating/created/deleting/restoring events |
| `app/Policies/` | Authorization policies — self-action prevention, protected resource checks, permission gates |
| `app/Providers/` | Service providers — app bootstrapping, tenancy events, telescope config |
| `app/Rules/` | Custom validation rule classes (e.g., `PasswordRule`) |
| `app/Services/` | Business logic — all query, create, update, delete operations live here |
| `app/Traits/` | Reusable trait across controllers (e.g., `PaginatesRequestTrait`) |
| `config/` | Application configuration — custom configs for permissions, protected resources, code generation |
| `database/factories/` | Model factories for seed and test data generation |
| `database/migrations/` | Schema migrations — central tables + Stancl tenancy + Spatie permissions |
| `database/seeders/` | Seeders for development/testing data — central and tenant contexts |
| `stubs/dev-resource/` | Stub templates for the `dev:resource` code generator |
| `.docs/postman/` | Importable Postman collections and environment presets |
| `.github/workflows/` | CI/CD pipeline definitions (test, quality gate, release)

---

## Requirements

- **PHP 8.3+**
- **Composer**
- **MySQL 8+** (or MariaDB, PostgreSQL, SQLite)
- **Node.js 22+** (for frontend asset building)
- **NPM**
- **Git**

---

## Installation Guide

### 1. Clone the Repository

```bash
git clone https://github.com/DiligentCreators/Laravel-Multitenancy-Api.git
cd Laravel-Multitenancy-Api
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Environment Variables

Configure these key variables in your `.env` file:

| Variable              | Description                     | Default                  |
| --------------------- | ------------------------------- | ------------------------ |
| `APP_NAME`            | Application name                | `Laravel`                |
| `APP_URL`             | Application URL                 | `http://localhost`       |
| `APP_ENV`             | Application environment         | `local`                  |
| `APP_DEBUG`           | Enable debug mode               | `true`                   |
| `DB_CONNECTION`       | Database driver                 | `mysql`                  |
| `DB_HOST`             | Database host                   | `127.0.0.1`              |
| `DB_PORT`             | Database port                   | `3306`                   |
| `DB_DATABASE`         | Database name                   | `laravel_multitenancy_api` |
| `DB_USERNAME`         | Database username               | `root`                   |
| `DB_PASSWORD`         | Database password               | —                        |
| `QUEUE_CONNECTION`    | Queue driver                    | `database`               |
| `CACHE_STORE`         | Cache driver                    | `database`               |
| `SESSION_DRIVER`      | Session driver                  | `database`               |

---

## Database Setup

Run the database migrations:

```bash
php artisan migrate
```

This will create all required tables including:

- `central_users` — Central platform administrators
- `users` — Tenant users
- `tenants` / `domains` — Stancl tenancy tables
- `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` — Spatie permission tables
- `personal_access_tokens` — Sanctum token storage
- `cache` / `cache_locks` — Cache storage
- `jobs` / `job_batches` / `failed_jobs` — Queue storage
- `sessions` — Session storage
- `telescope_entries` — Telescope debugging data

### Seed the Database

```bash
php artisan db:seed --class=Database\\Seeders\\Central\\CentralDatabaseSeeder
```

This seeds the central database with:

- **6 central users**: superadmin, tester, developer, admin, manager, staff (password: `password`)
- **Permissions** for users, tenants, and roles
- **6 roles**: superadmin, tester, developer, admin, manager, staff
- **Role-to-permission assignments**
- **User-to-role assignments**

---

## Storage Setup

```bash
php artisan storage:link
```

Creates the symbolic link from `public/storage` to `storage/app/public`.

---

## Queue Setup

The default queue driver is `database`. Start the queue worker:

```bash
php artisan queue:work
```

Queues are used for:

- Tenant provisioning (async creation of tenant resources)
- Password reset notifications
- Sanctum token pruning (scheduled daily)

---

## Running Locally

Use the development server:

```bash
php artisan serve
```

Or use the full development environment (server + queue + Vite):

```bash
composer dev
```

---

## Authentication

The platform uses **Laravel Sanctum** for API token authentication with two distinct guards:

| Guard         | Model         | Context  | Token Prefix  |
| ------------- | ------------- | -------- | ------------- |
| `central-api` | `CentralUser` | Central  | Central users |
| `tenant-api`  | `User`        | Tenant   | Tenant users  |

### Central Authentication

```bash
# Login
POST /api/central/v1/auth/login
{
    "email": "superadmin@example.com",
    "password": "password"
}

# Response: { "token": "1|abc123...", "user": { ... } }

# Get authenticated user
GET /api/central/v1/me
Authorization: Bearer 1|abc123...

# Logout
POST /api/central/v1/me/logout
Authorization: Bearer 1|abc123...
```

### Tenant Authentication

```bash
# Login
POST /api/tenant/v1/auth/login
{
    "email": "user@tenant.com",
    "password": "password"
}

# Response: { "token": "1|abc123...", "user": { ... } }

# Get authenticated user
GET /api/tenant/v1/me
Authorization: Bearer 1|abc123...

# Logout
POST /api/tenant/v1/me/logout
Authorization: Bearer 1|abc123...
```

All authenticated requests require the `Authorization: Bearer {token}` header. Tokens are returned in the login response.

---

## Roles & Permissions

### Central Roles & Permissions

Central permissions are defined in `config/central-permissions.php`:

```php
'users'   => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete', 'suspend', 'unsuspend'],
'tenants' => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete'],
'roles'   => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete'],
```

All central roles and permissions use `guard_name = 'central-api'`.

### Tenant Roles & Permissions

Tenant permissions are defined in `config/tenant-permissions.php`:

```php
'users' => ['create', 'read', 'update', 'delete'],
```

All tenant roles and permissions use `guard_name = 'tenant-api'`.

### Isolation Architecture

Central and tenant permissions are completely isolated:

- **Central users** can only be assigned central roles (`guard_name = 'central-api'`)
- **Tenant users** can only be assigned tenant roles (`guard_name = 'tenant-api'`)
- Spatie automatically scopes permission lookups by the user's guard
- Protected roles (`superadmin`, `tester`, `developer`) are hidden from listings and protected from modification

### Super Admin Bypass

Users with the `superadmin` role automatically pass all authorization gates via a `Gate::before` hook, except for self-targeted actions (delete own account, update own account, etc.) which are enforced by policy.

---

## Multitenancy

The platform uses **Stancl Tenancy** for multi-tenant functionality with a **single-database** isolation strategy.

### Tenant Identification

Tenants are identified through multiple strategies (in order of precedence):

1. **Domain** — The request hostname is matched against the `domains` table
2. **Header** — The `X-Tenant-Domain` or `X-Tenant` HTTP header
3. **Input** — The `tenant_domain` or `tenant_id` request parameter

This is handled by the `InitializeTenancy` middleware.

### Tenant Initialization Flow

```
Tenant Domain Request
    ↓
InitializeTenancy Middleware
    ↓
Identify Tenant (domain/header/input)
    ↓
Initialize Tenancy
    └── Set tenant_id in global scope
    └── Isolate cache (tenant-prefixed tags)
    └── Isolate filesystem (tenant-suffixed paths)
    ↓
Route Handler
```

### Tenant Provisioning

When a new tenant is created via `POST /api/central/v1/tenants`, the following happens automatically:

1. Tenant record is created with a UUID
2. Domain record is created
3. Tenant database context is initialized
4. Tenant-level permissions are created
5. Tenant-level roles are created
6. A superadmin user is created and assigned the `superadmin` role

### Protected Users

Central users with IDs defined in `config/central-protected-users.php` are:

- Excluded from user listings
- Blocked from being viewed, updated, deleted, restored, force-deleted, suspended, or unsuspended
- Protected regardless of the acting user's role

---

## API Documentation

Comprehensive API documentation is available as Postman collections:

```text
.docs/postman/
├── Central.postman_collection.json       # Central API endpoints
├── Central.postman_environment.json      # Central environment variables
├── Tenant.postman_collection.json        # Tenant API endpoints
└── Tenant.postman_environment.json       # Tenant environment variables
```

### How to Use

1. Open **Postman**
2. Click **Import** → **Import Files**
3. Select both the collection and environment JSON files
4. Set the active environment to `Central` or `Tenant`
5. Update the `base_url`, `email`, and `password` variables as needed
6. Start making requests

### API Versioning

The API is versioned using URL prefixing:

| Version | Base Path               |
| ------- | ----------------------- |
| v1      | `/api/central/v1/*`     |
| v1      | `/api/tenant/v1/*`      |

Future versions (`v2`, `v3`, etc.) can be added alongside by creating new route files.

### API Endpoints Overview

#### Central API (`/api/central/v1/`)

| Method   | Endpoint                    | Auth Required | Description              |
| -------- | --------------------------- | ------------- | ------------------------ |
| `POST`   | `/auth/login`               | No            | Login                    |
| `POST`   | `/auth/forgot-password`     | No            | Request password reset   |
| `POST`   | `/auth/reset-password`      | No            | Reset password           |
| `GET`    | `/me`                       | Yes           | Get authenticated user   |
| `POST`   | `/me`                       | Yes           | Update profile           |
| `POST`   | `/me/change-password`       | Yes           | Change password          |
| `POST`   | `/me/logout`                | Yes           | Logout                   |
| `GET`    | `/dashboard`                | Yes           | Dashboard stats          |
| `GET`    | `/tenants`                  | Yes           | List tenants             |
| `POST`   | `/tenants`                  | Yes           | Create tenant            |
| `GET`    | `/tenants/{tenant}`         | Yes           | Get tenant               |
| `PUT`    | `/tenants/{tenant}`         | Yes           | Update tenant            |
| `DELETE` | `/tenants/{tenant}`         | Yes           | Delete tenant            |
| `POST`   | `/tenants/{tenant}/restore` | Yes           | Restore tenant           |
| `DELETE` | `/tenants/{tenant}/force`   | Yes           | Force delete tenant      |
| `GET`    | `/roles`                    | Yes           | List roles               |
| `POST`   | `/roles`                    | Yes           | Create role              |
| `GET`    | `/roles/{role}`             | Yes           | Get role                 |
| `PUT`    | `/roles/{role}`             | Yes           | Update role              |
| `DELETE` | `/roles/{role}`             | Yes           | Delete role              |
| `GET`    | `/users`                    | Yes           | List users               |
| `POST`   | `/users`                    | Yes           | Create user              |
| `GET`    | `/users/{user}`             | Yes           | Get user                 |
| `PUT`    | `/users/{user}`             | Yes           | Update user              |
| `DELETE` | `/users/{user}`             | Yes           | Delete user              |
| `POST`   | `/users/{user}/restore`     | Yes           | Restore user             |
| `DELETE` | `/users/{user}/force`       | Yes           | Force delete user        |
| `POST`   | `/users/{user}/suspend`     | Yes           | Suspend user             |
| `POST`   | `/users/{user}/unsuspend`   | Yes           | Unsuspend user           |
| `POST`   | `/users/{user}/change-password` | Yes       | Change user password     |

#### Tenant API (`/api/tenant/v1/`)

| Method   | Endpoint                    | Auth Required | Description              |
| -------- | --------------------------- | ------------- | ------------------------ |
| `POST`   | `/auth/login`               | No            | Login                    |
| `POST`   | `/auth/forgot-password`     | No            | Request password reset   |
| `POST`   | `/auth/reset-password`      | No            | Reset password           |
| `GET`    | `/me`                       | Yes           | Get authenticated user   |
| `POST`   | `/me`                       | Yes           | Update profile           |
| `POST`   | `/me/change-password`       | Yes           | Change password          |
| `POST`   | `/me/logout`                | Yes           | Logout                   |
| `GET`    | `/dashboard`                | Yes           | Dashboard stats          |

---

## Testing

The project uses **Pest PHP** for testing.

```bash
# Run all tests
php artisan test

# Run tests with Pest directly
vendor/bin/pest

# Filter tests by name
php artisan test --filter=LoginTest

# Run with compact output
php artisan test --compact
```

### Test Structure

```
tests/
├── Pest.php                   # Test case configuration (RefreshDatabase for Feature tests)
├── Unit/                      # Unit tests
└── Feature/
    └── Central/
        └── Auth/
            └── LoginTest.php  # Central authentication tests
```

### Running Tests in CI

The repository includes two CI workflows:

- **`laravel.yml`** — Runs `php artisan test` on push/PR to `main`
- **`quality-gate.yml`** — Runs Pint (code style), PHPStan (static analysis, level 5), and checks for dirty files on PR to `main`/`develop`

---

## Code Generation (`dev:resource`)

The `php artisan dev:resource` command is the backbone of this project — it scaffolds a complete CRUD resource in seconds with consistent architecture, naming conventions, and project patterns.

### Usage

```bash
# Interactive mode (recommended)
php artisan dev:resource

# Non-interactive mode
php artisan dev:resource Contact --context=tenant --v=v1

# Save selected generators as defaults
php artisan dev:resource --save-defaults
```

### Interactive Workflow

Running `dev:resource` without arguments launches an interactive prompt:

1. **Resource Name** — e.g., `Contact`, `Invoice`, `Product`
2. **Context** — `Central` or `Tenant` (determines route prefix, guard, model namespace)
3. **API Version** — `V1` or `V2` (for future versioning)
4. **Module Path** — Optional subdirectory within the version namespace
5. **Generators** — Multi-select from: controller, service, model, request, resource, migration, factory, policy, observer, permissions, repository, action, dto, enum, test
6. **Migration** — Confirm whether to generate a migration
7. **Confirm** — Review and confirm generation

### What It Generates

| Generator    | File(s) Created                                                       |
| ------------ | --------------------------------------------------------------------- |
| Controller   | `app/Http/Controllers/{Context}/Api/{Version}/{Name}Controller.php`   |
| Service      | `app/Services/{Context}/{Name}Service.php`                            |
| Model        | `app/Models/{Name}.php`                                               |
| Request      | `app/Http/Requests/{Context}/Api/{Version}/{Name}/Store{Name}Request.php` / `Update{Name}Request.php` |
| Resource     | `app/Http/Resources/{Context}/Api/{Version}/{Name}/{Name}Resource.php` / `List{Name}Resource.php` |
| Migration    | `database/migrations/{timestamp}_create_{table}_table.php`            |
| Factory      | `database/factories/{Context}/{Name}Factory.php`                      |
| Policy       | `app/Policies/{Name}Policy.php`                                       |
| Observer     | `app/Observers/{Name}Observer.php`                                    |
| Permissions  | Updates `config/central-permissions.php` or `config/tenant-permissions.php` |
| Repository   | `app/Repositories/{Context}/{Name}Repository.php`                     |
| Action       | `app/Actions/{Context}/{Name}Action.php`                              |
| DTO          | `app/DTOs/{Context}/{Name}Dto.php`                                    |
| Enum         | `app/Enums/{Context}/{Name}Enum.php`                                  |
| Test         | `tests/Feature/{Context}/{Name}Test.php`                              |

### Template System

All generated files are rendered from stubs located in `stubs/dev-resource/`. Each stub uses `{{ placeholders }}` that are automatically populated based on the resource name, context, version, and path:

```
stubs/dev-resource/
├── action.stub
├── controller.api.stub
├── dto.stub
├── enum.stub
├── factory.stub
├── list.resource.stub
├── migration.stub
├── model.stub
├── observer.stub
├── policy.stub
├── repository.stub
├── resource.stub
├── service.stub
├── store.request.stub
├── test.stub
└── update.request.stub
```

### Placeholder Reference

| Placeholder              | Example (Name=Contact, Context=Tenant)       |
| ------------------------ | -------------------------------------------- |
| `{{ name }}`             | `Contact`                                    |
| `{{ nameVariable }}`     | `contact`                                    |
| `{{ nameSnake }}`        | `contact`                                    |
| `{{ nameSnakePlural }}`  | `contacts`                                   |
| `{{ nameKebab }}`        | `contact`                                    |
| `{{ nameKebabPlural }}`  | `contacts`                                   |
| `{{ context }}`          | `Tenant`                                     |
| `{{ contextLower }}`     | `tenant`                                     |
| `{{ version }}`          | `V1`                                         |
| `{{ versionLower }}`     | `v1`                                         |
| `{{ model }}`            | `Contact`                                    |
| `{{ modelVariable }}`    | `contact`                                    |
| `{{ modelPlural }}`      | `contacts`                                   |
| `{{ table }}`            | `contacts`                                   |
| `{{ userModel }}`        | `User` (or `CentralUser` for central context)|
| `{{ userModelNamespace }}` | `App\Models\User`                          |

### Customizing Default Generators

Configure which generators run by default in `config/dev-resource.php`:

```php
'default_context' => 'central',

'default_generators' => [
    'controller', 'service', 'model', 'request', 'resource',
    'migration', 'factory', 'policy', 'observer', 'permissions',
],
```

Use `--save-defaults` to persist your selection from an interactive session.

---

## Configuration Conventions

The project uses dedicated config files for resource protection and permission definitions. These serve as both runtime configuration and documentation of the resource inventory.

### Permission Config Files

Define available permissions per resource in dedicated config files. The `dev:resource --permissions` generator automatically maintains these.

**`config/central-permissions.php`** — Central API resource permissions:

```php
'users'   => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete', 'suspend', 'unsuspend'],
'tenants' => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete'],
'roles'   => ['list', 'create', 'read', 'update', 'delete', 'restore', 'force.delete'],
```

**`config/tenant-permissions.php`** — Tenant API resource permissions:

```php
'users' => ['create', 'read', 'update', 'delete'],
```

### Protected Resources Config Files

Define resources that should be protected from listing and modification.

**`config/central-protected-users.php`** — User IDs excluded from listings and modification:

```php
'protected' => [
    '1',   // superadmin
    '2',   // tester
    '3',   // developer
],
```

**`config/central-protected-roles.php`** — Role names excluded from listings and modification:

```php
'protected' => [
    'superadmin',
    'tester',
    'developer',
],
```

These are enforced in services (query filtering) and policies (authorization denial). When adding a new protected resource type, follow this same pattern.

### Sanctum Abilities Config

**`config/abilities.php`** — Defines all Sanctum token abilities organized by context and resource:

```php
'central' => [
    'tenant' => [
        'create' => 'central:tenant:create',
        'manage' => 'central:tenant:manage',
    ],
    'users' => ['manage' => 'central:users:manage'],
    // ...
],
'tenant' => [
    'contacts' => [
        'create' => 'tenant:contacts:create',
        'manage' => 'tenant:contacts:manage',
    ],
    // ...
],
```

---

## Development Guidelines

### Services

Business logic belongs in dedicated **Service classes** (`app/Services/`). Controllers delegate to services for data operations.

### Controllers

Controllers remain **thin**. They handle:

- Request dispatch via `Gate::authorize()`
- Input validation via Form Requests
- Delegation to Service classes
- Response formatting via API Resources

### Form Requests

All input validation is handled by **Form Request** classes (`app/Http/Requests/`). Each controller action with user input has a dedicated request class.

### Policies

Authorization is handled by **Policy** classes (`app/Models/Policies/`). Policies check:

1. Self-targeting prevention (users cannot act on themselves)
2. Protected resource status (configurable protected users/roles)
3. Permission gates (`$user->can('resource.action')`)

### API Resources

JSON responses are formatted using **API Resource** classes (`app/Http/Resources/`). The `ApiResponseService` provides consistent response structures with `status`, `message`, `data`, and `meta` fields.

### Migrations

Migrations follow the standard Laravel convention. Tenant-scoped migrations can be added to `database/migrations/tenant/` with the `--path` parameter.

---

## Code Quality

The project enforces code quality through automated tools:

| Tool         | Purpose                    | Command                    |
| ------------ | -------------------------- | -------------------------- |
| Laravel Pint | Code style fixing          | `vendor/bin/pint`          |
| Larastan     | Static analysis (Level 5)  | `vendor/bin/phpstan analyse` |

---

## Common Commands

| Command                                    | Description                       |
| ------------------------------------------ | --------------------------------- |
| `php artisan serve`                        | Start development server          |
| `php artisan migrate`                      | Run database migrations           |
| `php artisan migrate:rollback`             | Rollback last migration           |
| `php artisan db:seed`                      | Seed database                     |
| `php artisan test`                         | Run tests                         |
| `vendor/bin/pint`                          | Fix code style                    |
| `vendor/bin/phpstan analyse`               | Run static analysis               |
| `php artisan queue:work`                   | Start queue worker                |
| `php artisan queue:listen --tries=1`       | Listen for queue jobs             |
| `php artisan optimize:clear`               | Clear all cached data             |
| `php artisan storage:link`                 | Create storage symlink            |
| `php artisan key:generate`                 | Generate application key          |
| `php artisan tinker`                       | Interactive PHP shell             |
| `php artisan telescope:install`            | Install Telescope                 |
| `php artisan pail`                         | Tail application logs             |
| `composer dev`                             | Full dev environment              |

---

## Troubleshooting

### Tenant Not Initializing

```
Verify that:
- The domain is registered in the `domains` table
- The URL matches the tenant's domain
- The `X-Tenant-Domain` or `X-Tenant` header is set correctly
```

### Migration Issues

```
If migrations fail:
1. Run `php artisan migrate:fresh` to reset all tables
2. Check your database connection in `.env`
3. Ensure MySQL 8+ is running
```

### Permission Cache

```
After modifying roles or permissions:
php artisan optimize:clear
```

Or flush the Spatie cache programmatically:

```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

### Queue Failures

```
Check failed jobs:
php artisan queue:failed

Retry failed jobs:
php artisan queue:retry all
```

### Storage Issues

```
If uploaded files are not accessible:
php artisan storage:link
```

---

## Contributing

Contributions are welcome! Please follow these steps:

1. **Fork** the repository
2. **Create a feature branch** from `develop`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** following the development guidelines
4. **Run tests** to ensure nothing is broken:
   ```bash
   php artisan test
   ```
5. **Run code style and static analysis**:
   ```bash
   vendor/bin/pint
   vendor/bin/phpstan analyse
   ```
6. **Commit your changes** with a clear, descriptive message
7. **Open a Pull Request** to the `develop` branch

### Pull Request Guidelines

- Keep PRs focused on a single concern
- Include tests for new functionality
- Update Postman collections if API endpoints change
- Follow existing code conventions
- Ensure the quality gate passes

---

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## Additional Documentation

For detailed documentation on specific topics, see the following resources:

### Postman API Collections

```text
.docs/postman/
├── Central.postman_collection.json       # Complete Central API endpoint collection
├── Central.postman_environment.json      # Central environment variables preset
├── Tenant.postman_collection.json        # Complete Tenant API endpoint collection
└── Tenant.postman_environment.json       # Tenant environment variables preset
```

### Code Generation Stubs

```text
stubs/dev-resource/                       # 16 stub templates for dev:resource generator
```

### CI/CD Workflows

```text
.github/workflows/
├── laravel.yml                           # Test suite on push/PR to main
├── quality-gate.yml                      # Pint + PHPStan + dirty check on PR
├── release.yml                           # Changelog generation on release
├── auto-label.yml                        # Auto-label PRs by changed paths
└── auto-merge-dependabot.yml             # Auto-merge Dependabot PRs
```
