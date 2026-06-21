<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\NotePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(NotePolicy::class)]
class Note extends Model
{
    use BelongsToTenant, Searchable, SoftDeletes;

    protected $table = 'crm_notes';

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'team_id',
        'created_by',
        'updated_by',
        'noteable_type',
        'noteable_id',
        'content',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
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

    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function toSearchableArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
