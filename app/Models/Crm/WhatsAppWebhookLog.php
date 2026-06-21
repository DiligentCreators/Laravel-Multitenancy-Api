<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\WhatsAppWebhookLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(WhatsAppWebhookLogPolicy::class)]
class WhatsAppWebhookLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'crm_whatsapp_webhook_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'event_type',
        'payload',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
