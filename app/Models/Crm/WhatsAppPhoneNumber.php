<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\WhatsAppPhoneNumberPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(WhatsAppPhoneNumberPolicy::class)]
class WhatsAppPhoneNumber extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'crm_whatsapp_phone_numbers';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
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

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_phone_number_id');
    }
}
