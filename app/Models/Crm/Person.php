<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Policies\Crm\PersonPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[UsePolicy(PersonPolicy::class)]
class Person extends Model
{
    use BelongsToTenant, Searchable, SoftDeletes;

    protected $table = 'crm_people';

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'team_id',
        'created_by',
        'updated_by',
        'status_id',
        'source_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'json',
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'crm_taggables');
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'crm_organization_people')
            ->withPivot('role', 'is_primary', 'start_date', 'end_date')
            ->withTimestamps();
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function toSearchableArray(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
        ];
    }
}
