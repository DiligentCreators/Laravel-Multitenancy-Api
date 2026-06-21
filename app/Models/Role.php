<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Role as ModelsRole;

/**
 * @property string|null $tenant_id
 */
class Role extends ModelsRole
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    //
}
