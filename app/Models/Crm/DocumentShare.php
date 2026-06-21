<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\DocumentSharePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(DocumentSharePolicy::class)]
class DocumentShare extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'crm_document_shares';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'share_token',
        'expires_at',
        'password_protected',
        'password',
        'access_count',
        'last_accessed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'password_protected' => 'boolean',
            'access_count' => 'integer',
            'last_accessed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
