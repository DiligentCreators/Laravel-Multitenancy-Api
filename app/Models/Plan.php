<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\PlanObserver;
use App\Policies\PlanPolicy;
use Database\Factories\Central\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(PlanFactory::class)]
#[ObservedBy(PlanObserver::class)]
#[UsePolicy(PlanPolicy::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'trial_days',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /** @param Plan $query */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            Feature::class,
            'plan_features'
        )
            ->using(PlanFeature::class)
            ->withPivot('value')
            ->withTimestamps();
    }

    public function hasFeature(string $slug): bool
    {
        $feature = $this->features()
            ->where('slug', $slug)
            ->first();

        if (! $feature) {
            return false;
        }

        return filter_var(
            $feature->pivot->getAttribute('value'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function getFeatureValue(string $slug): mixed
    {
        $feature = $this->features()
            ->where('slug', $slug)
            ->first();

        return $feature?->pivot->getAttribute('value');
    }
}
