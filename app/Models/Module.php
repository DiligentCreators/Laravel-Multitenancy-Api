<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\Central\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(ModuleFactory::class)]
class Module extends Model
{
    use HasFactory, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'is_enabled',
        'dependencies',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'dependencies' => 'array',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_module')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }

    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    public function enableForTenant(Tenant $tenant): void
    {
        $this->tenants()->syncWithoutDetaching([$tenant->id => ['is_enabled' => true]]);
    }

    public function disableForTenant(Tenant $tenant): void
    {
        $this->tenants()->updateExistingPivot($tenant->id, ['is_enabled' => false]);
    }
}
