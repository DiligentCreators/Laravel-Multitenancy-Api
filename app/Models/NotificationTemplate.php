<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Central\NotificationChannelEnum;
use App\Policies\NotificationTemplatePolicy;
use Database\Factories\Central\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

#[UseFactory(NotificationTemplateFactory::class)]
#[UsePolicy(NotificationTemplatePolicy::class)]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'channel',
        'title',
        'message',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelEnum::class,
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
