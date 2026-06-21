<?php

namespace App\Models\Crm;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Policies\Crm\CustomFieldDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(CustomFieldDefinitionPolicy::class)]
class CustomFieldDefinition extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_custom_field_definitions';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'name',
        'key',
        'type',
        'options',
        'is_required',
        'is_unique',
        'validation_rules',
        'default_value',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'validation_rules' => 'json',
            'default_value' => 'json',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
