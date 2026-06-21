<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\SettingDefinitionObserver;
use App\Policies\SettingDefinitionPolicy;
use Database\Factories\Central\SettingDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(SettingDefinitionFactory::class)]
#[ObservedBy(SettingDefinitionObserver::class)]
#[UsePolicy(SettingDefinitionPolicy::class)]
class SettingDefinition extends Model
{
    /** @use HasFactory<SettingDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'label',
        'type',
        'default_value',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param SettingDefinition $query */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    protected static function newFactory()
    {
        return SettingDefinitionFactory::new();
    }
}
