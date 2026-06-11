<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as ModelsPermission;

/**
 * @property string|null $scope
 * @property string|null $tenant_id
 * @property bool $is_assigned
 */
class Permission extends ModelsPermission
{
    //
}
