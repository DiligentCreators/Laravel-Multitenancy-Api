<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\FeatureObserver;
use App\Policies\FeaturePolicy;
use Database\Factories\Central\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(FeatureFactory::class)]
#[ObservedBy(FeatureObserver::class)]
#[UsePolicy(FeaturePolicy::class)]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @param Feature $query */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }
}
