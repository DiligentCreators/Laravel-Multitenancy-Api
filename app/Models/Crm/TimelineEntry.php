<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\TimelineEntryPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Scout\Searchable;

#[UsePolicy(TimelineEntryPolicy::class)]
class TimelineEntry extends Model
{
    use BelongsToTenant, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    protected $table = 'crm_timeline_entries';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'event_type',
        'title',
        'description',
        'meta',
        'caused_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'occurred_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by');
    }
}
