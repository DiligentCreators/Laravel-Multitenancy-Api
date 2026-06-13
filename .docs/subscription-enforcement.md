# Subscription Enforcement System

## Overview

The Subscription Enforcement system controls access to tenant API routes based on subscription status and plan features. It consists of middleware, services, model helpers, and enums.

---

## Middleware Usage

### `subscription` Middleware

Ensures the current tenant has a valid, active, non-expired subscription.

**Registration** — `bootstrap/app.php`:

```php
'subscription' => EnsureTenantSubscription::class,
```

**Usage in routes:**

```php
Route::middleware(['auth:tenant-api', 'subscription'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::apiResource('invoices', InvoiceController::class);
});
```

### `feature:{slug}` Middleware

Gates access based on whether the tenant's plan includes a specific feature.

**Registration** — `bootstrap/app.php`:

```php
'feature' => EnsurePlanFeature::class,
```

**Usage in routes:**

```php
// Only available if plan includes 'reports' feature
Route::middleware(['auth:tenant-api', 'subscription', 'feature:reports'])->group(function () {
    Route::get('reports', [ReportController::class, 'index']);
});

// Only available if plan includes 'users' feature
Route::middleware(['auth:tenant-api', 'subscription', 'feature:users'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

**Middleware order matters:** Always place `auth` first, then `subscription`, then `feature` (if needed).

---

## Subscription Lifecycle

### States

| Status | Description | Access Granted |
|--------|-------------|---------------|
| `trial` | Free trial period | Yes (if not expired) |
| `active` | Paid subscription | Yes (if not expired) |
| `expired` | Past the `ends_at` date | No |
| `cancelled` | Manually cancelled | No |
| `suspended` | Administrative suspension (non-payment, abuse) | No |

### State Transitions

```
trial ──(subscribe)──> active ──(cancel)──> cancelled
                         │
                         ├──(non-payment)──> suspended
                         │
                         └──(past due)──> expired
```

### Expiration Check

Expiration is determined at runtime by comparing `ends_at` against `Carbon::now()`. A subscription with `ends_at` in the past is considered expired regardless of its status enum value. Subscriptions with `ends_at = null` never expire.

Helper methods on the `Subscription` model:

```php
$subscription->isActive();         // status=active + not expired
$subscription->isTrial();          // status=trial + not expired
$subscription->isExpired();        // ends_at is in the past
$subscription->isCancelled();      // status=cancelled
$subscription->isSuspended();      // status=suspended
$subscription->isCurrentlyActive(); // isActive() || isTrial()
$subscription->daysRemaining();    // days until ends_at
```

---

## Plan Feature Architecture

### Storage Strategy: `plan_features` Pivot Table

The system uses a **separate pivot table** (`plan_features`) rather than a JSON column on plans. This was chosen for:

| Approach | Pros | Cons |
|----------|------|------|
| **JSON column** | Simple, no extra table, single query | Hard to query/aggregate, schema-less, migration-heavy per feature |
| **Separate pivot table** ✅ | Queryable, extensible, typed values via `type`, normalized, indexable | More joins, additional model |

### Schema

- `features` table: defines available features (name, slug, type, is_active)
- `plan_features` table: pivot linking plans to features with a `value` column
- `Feature.type` determines how to interpret `value`: `boolean` (toggle), `integer` (limit), `decimal` (numeric), `string` (config)

### Helper Methods on `Plan` Model

```php
$plan->hasFeature('reports');        // bool — checks boolean value
$plan->getFeatureValue('users_limit'); // mixed — raw value from pivot
```

### Helper Methods on `SubscriptionService`

```php
$service->hasFeature($tenant, 'reports');     // bool
$service->featureValue($tenant, 'users_limit'); // mixed
```

---

## Usage Limit Architecture

### Design

The `SubscriptionLimitService` provides a reusable architecture for enforcing numeric limits defined as plan features.

### Available Limit Checks

| Method | Feature Slug | Description |
|--------|-------------|-------------|
| `canAddUser($tenant)` | `users_limit` | Max tenant users |
| `canAddContact($tenant)` | `contacts_limit` | Max contacts |
| `canAddTeam($tenant)` | `teams_limit` | Max teams |
| `canUseStorage($tenant, $bytes)` | `storage_limit` | Max storage in MB |

### Service Methods

```php
$limitService = app(SubscriptionLimitService::class);

// Generic check
$result = $limitService->checkLimit($tenant, 'users_limit', $currentCount);

// Specific checks
$result = $limitService->canAddUser($tenant);
$result = $limitService->canAddContact($tenant);

// Result format:
[
    'allowed' => true|false,
    'current' => 5,    // current usage count
    'limit' => 10,     // plan limit
    'message' => '...', // human-readable message
]
```

### Usage in Controllers

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $limitCheck = $this->subscriptionLimitService->canAddUser(tenant());

    if (! $limitCheck['allowed']) {
        return $this->api->error($limitCheck['message'], 403);
    }

    // Proceed with creation...
}
```

### Adding New Limits

1. Create a new feature in the `features` table with `type = 'integer'`
2. Attach it to plans via `plan_features` with the desired limit value
3. Add a helper method on `SubscriptionLimitService` (optional)

---

## API Error Responses

All subscription enforcement errors follow the project's standard `ApiResponseService` format:

```json
// No subscription — 402
{ "status": "error", "message": "No active subscription found. Please subscribe to a plan.", "errors": [] }

// Expired — 402
{ "status": "error", "message": "Your subscription has expired. Please renew to continue.", "errors": [] }

// Suspended — 403
{ "status": "error", "message": "Your subscription has been suspended. Please contact support.", "errors": [] }

// Cancelled — 402
{ "status": "error", "message": "Your subscription has been cancelled. Please subscribe again.", "errors": [] }

// Feature unavailable — 403
{ "status": "error", "message": "The 'reports' feature is not available on your current plan.", "errors": [] }

// Limit reached — 403
{ "status": "error", "message": "users_limit limit reached (10/10). Please upgrade your plan.", "errors": [] }
```

---

## Testing

See `tests/Feature/Central/Subscription/SubscriptionEnforcementTest.php` for full test coverage.

```bash
# Run subscription enforcement tests
php artisan test --compact --filter=SubscriptionEnforcement

# Run all tests
php artisan test --compact
```

Test scenarios covered:
- Active subscription → 200
- Trial subscription → 200
- No subscription → 402
- Expired subscription → 402
- Suspended subscription → 403
- Cancelled subscription → 402
