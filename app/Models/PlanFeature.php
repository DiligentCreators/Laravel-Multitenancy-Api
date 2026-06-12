<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string|null $value
 */
class PlanFeature extends Pivot
{
    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
    ];
}
