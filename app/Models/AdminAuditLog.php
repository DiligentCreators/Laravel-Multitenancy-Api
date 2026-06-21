<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'central_user_id',
        'action',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    /** @return BelongsTo<CentralUser, $this> */
    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'central_user_id');
    }
}
