<?php

namespace App\Models\Crm;

use App\Enums\ConversationChannelEnum;
use App\Enums\ConversationStatusEnum;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\ConversationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

#[UsePolicy(ConversationPolicy::class)]
class Conversation extends Model
{
    use BelongsToTenant, HasFactory, Searchable, SoftDeletes;

    protected $table = 'crm_conversations';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'subject',
        'channel',
        'status',
        'last_message_at',
        'metadata',
        'owner_id',
        'team_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ConversationChannelEnum::class,
            'status' => ConversationStatusEnum::class,
            'last_message_at' => 'datetime',
            'metadata' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            if (! $conversation->uuid) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
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

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'subject' => $this->subject,
        ];
    }
}
