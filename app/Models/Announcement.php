<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AnnouncementObserver;
use App\Policies\AnnouncementPolicy;
use Database\Factories\Central\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(AnnouncementFactory::class)]
#[ObservedBy(AnnouncementObserver::class)]
#[UsePolicy(AnnouncementPolicy::class)]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'title',
        'content',
        'type',
        'starts_at',
        'ends_at',
        'is_active',
        'audience_type',
        'audience_ids',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'audience_ids' => 'array',
        ];
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
