<?php

namespace App\Models\Crm;

use App\Enums\RecurringFrequencyEnum;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringEventPattern extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'crm_recurring_event_patterns';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'updated_by',
        'frequency',
        'interval',
        'ends_at',
        'occurrences_limit',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequencyEnum::class,
            'interval' => 'integer',
            'ends_at' => 'date',
            'occurrences_limit' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'recurring_event_pattern_id');
    }
}
