<?php

namespace App\Models\Crm;

use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\PortalPersonLinkPolicy;
use Database\Factories\Crm\PortalPersonLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PortalPersonLinkFactory::class)]
#[UsePolicy(PortalPersonLinkPolicy::class)]
class PortalPersonLink extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'portal_person_links';

    protected $fillable = [
        'tenant_id',
        'portal_user_id',
        'person_id',
        'organization_id',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
