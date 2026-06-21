<?php

namespace App\Models\Crm;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderEnum;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\WhatsAppAccountPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(WhatsAppAccountPolicy::class)]
class WhatsAppAccount extends Model
{
    use BelongsToTenant, HasFactory, Searchable, SoftDeletes;

    protected $table = 'crm_whatsapp_accounts';

    protected $attributes = [
        'provider' => 'meta_cloud',
        'status' => 'active',
    ];

    protected $fillable = [
        'tenant_id',
        'provider',
        'business_account_id',
        'app_id',
        'app_secret',
        'access_token',
        'webhook_verify_token',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'provider' => WhatsAppProviderEnum::class,
            'status' => WhatsAppAccountStatusEnum::class,
            'app_secret' => 'encrypted',
            'access_token' => 'encrypted',
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

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WhatsAppWebhookLog::class, 'whatsapp_account_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'business_account_id' => $this->business_account_id,
            'app_id' => $this->app_id,
        ];
    }
}
