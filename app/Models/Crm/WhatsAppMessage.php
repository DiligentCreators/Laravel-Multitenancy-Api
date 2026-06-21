<?php

namespace App\Models\Crm;

use App\Enums\WhatsAppMessageDirectionEnum;
use App\Enums\WhatsAppMessageStatusEnum;
use App\Enums\WhatsAppMessageTypeEnum;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\WhatsAppMessagePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

#[UsePolicy(WhatsAppMessagePolicy::class)]
class WhatsAppMessage extends Model
{
    use BelongsToTenant, HasFactory, Searchable;

    protected $table = 'crm_whatsapp_messages';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'person_id',
        'whatsapp_phone_number_id',
        'provider_message_id',
        'direction',
        'type',
        'from_number',
        'to_number',
        'content',
        'media_url',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'direction' => WhatsAppMessageDirectionEnum::class,
            'type' => WhatsAppMessageTypeEnum::class,
            'status' => WhatsAppMessageStatusEnum::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function whatsappPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'content' => $this->content,
            'from_number' => $this->from_number,
            'to_number' => $this->to_number,
        ];
    }
}
