<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\MessageTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(MessageTemplatePolicy::class)]
class MessageTemplate extends Model
{
    use BelongsToTenant, HasFactory, Searchable, SoftDeletes;

    protected $table = 'crm_message_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'channel',
        'category',
        'body',
        'variables',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'json',
            'is_active' => 'boolean',
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

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'body' => $this->body,
        ];
    }
}
