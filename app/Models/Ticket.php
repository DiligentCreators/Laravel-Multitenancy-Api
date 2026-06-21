<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\TicketObserver;
use App\Policies\TicketPolicy;
use Database\Factories\Central\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UseFactory(TicketFactory::class)]
#[ObservedBy(TicketObserver::class)]
#[UsePolicy(TicketPolicy::class)]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withTrashed(), $value, $field);
    }

    protected $fillable = [
        'ticket_number',
        'tenant_id',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
        ];
    }
}
