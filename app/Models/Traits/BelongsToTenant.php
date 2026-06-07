<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Apply this trait to any model that belongs to a tenant.
 *
 * Provides:
 *   1. A global TenantScope that auto-filters queries by tenant_id
 *   2. Automatic tenant_id assignment on creation
 *   3. A tenant() BelongsTo relationship
 *
 * Usage:
 *   class Contact extends Model
 *   {
 *       use BelongsToTenant;
 *   }
 *
 *   Contact::all() → SELECT * FROM contacts WHERE tenant_id = ?
 *   Contact::create([...]) → auto-fills tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (tenancy()->initialized && ! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', tenant()->id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
