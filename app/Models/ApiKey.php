<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ApiKeyObserver;
use App\Policies\ApiKeyPolicy;
use Database\Factories\Central\ApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

#[UseFactory(ApiKeyFactory::class)]
#[ObservedBy(ApiKeyObserver::class)]
#[UsePolicy(ApiKeyPolicy::class)]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'name',
        'key',
        'permissions',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function generateKey(): string
    {
        return Str::random(64);
    }

    public function regenerate(): string
    {
        $newKey = self::generateKey();
        $this->update(['key' => $newKey]);

        return $newKey;
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
