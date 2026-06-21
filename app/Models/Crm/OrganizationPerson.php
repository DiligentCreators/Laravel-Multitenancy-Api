<?php

namespace App\Models\Crm;

use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\OrganizationPersonPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(OrganizationPersonPolicy::class)]
class OrganizationPerson extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_organization_people';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'person_id',
        'role',
        'is_primary',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
