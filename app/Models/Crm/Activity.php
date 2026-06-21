<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\ActivityPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(ActivityPolicy::class)]
class Activity extends Model
{
    use BelongsToTenant, Searchable, SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'team_id',
        'created_by',
        'updated_by',
        'activityable_type',
        'activityable_id',
        'type',
        'subject',
        'description',
        'starts_at',
        'ends_at',
        'completed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activityable(): MorphTo
    {
        return $this->morphTo();
    }

    public const TYPES = ['call', 'meeting', 'email', 'whatsapp', 'sms', 'visit', 'task', 'custom'];

    public function toSearchableArray(): array
    {
        return [
            'subject' => $this->subject,
            'description' => $this->description,
        ];
    }
}
