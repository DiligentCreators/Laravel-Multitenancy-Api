<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\SettingPolicy;
use Database\Factories\Central\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

#[UseFactory(SettingFactory::class)]
#[UsePolicy(SettingPolicy::class)]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, Searchable;

    protected $table = 'system_settings';

    protected $fillable = [
        'group_id',
        'key',
        'label',
        'value',
        'type',
        'default_value',
        'validation_rules',
        'is_public',
        'is_encrypted',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
        ];
    }
}
