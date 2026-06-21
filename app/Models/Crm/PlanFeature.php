<?php

namespace App\Models\Crm;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $table = 'crm_plan_features';

    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FeatureDefinition::class, 'feature_id');
    }
}
