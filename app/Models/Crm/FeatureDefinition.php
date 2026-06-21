<?php

namespace App\Models\Crm;

use App\Policies\Crm\FeatureDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[UsePolicy(FeatureDefinitionPolicy::class)]
class FeatureDefinition extends Model
{
    protected $table = 'crm_feature_definitions';

    protected $fillable = [
        'key',
        'name',
        'type',
        'default_value',
        'is_usage_limit',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'json',
            'is_usage_limit' => 'boolean',
        ];
    }
}
