<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\SettingGroupPolicy;
use Database\Factories\Central\SettingGroupFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[UseFactory(SettingGroupFactory::class)]
#[UsePolicy(SettingGroupPolicy::class)]
class SettingGroup extends Model
{
    /** @use HasFactory<SettingGroupFactory> */
    use HasFactory, Searchable;

    protected $table = 'settings_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'group_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
