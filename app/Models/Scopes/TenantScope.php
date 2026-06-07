<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/*
 * Automatically scopes all queries to the current tenant.
 *
 * When tenancy is initialized, every query on tenant-scoped models
 * gets WHERE tenant_id = current_tenant appended automatically.
 *
 * In central context (tenancy not initialized), the scope is a no-op
 * so central code can query across tenants when needed.
 *
 * To bypass: Model::withoutGlobalScope(TenantScope::class)->get()
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (tenancy()->initialized) {
            $builder->where($model->getTable().'.tenant_id', tenant()->id);
        }
    }
}
