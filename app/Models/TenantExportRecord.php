<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExportRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'central_user_id',
        'type',
        'format',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<CentralUser, $this> */
    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'central_user_id');
    }
}
