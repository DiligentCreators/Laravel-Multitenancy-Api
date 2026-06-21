<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends Model
{
    protected $fillable = [
        'tenant_id',
        'feature',
        'used',
        'limit',
        'period',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'limit' => 'integer',
            'reset_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
