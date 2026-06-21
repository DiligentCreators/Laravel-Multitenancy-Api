<?php

namespace App\Models\Crm;

use App\Enums\MessageDirectionEnum;
use App\Enums\MessageStatusEnum;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\MessagePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(MessagePolicy::class)]
class Message extends Model
{
    use BelongsToTenant, HasFactory, Searchable, SoftDeletes;

    protected $table = 'crm_messages';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'sender_type',
        'sender_id',
        'direction',
        'body',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'metadata',
        'owner_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirectionEnum::class,
            'status' => MessageStatusEnum::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'metadata' => 'json',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
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

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'body' => $this->body,
        ];
    }
}
