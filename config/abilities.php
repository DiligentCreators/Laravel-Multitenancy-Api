<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NOTICE: Sanctum Token Abilities
|--------------------------------------------------------------------------
|
| This application uses Spatie Laravel Permission for authorization.
| Sanctum token abilities are NOT used. Authorization is handled via:
|
|   - Policies (app/Policies/)
|   - Gate::authorize() in controllers
|   - Spatie's @can / ->can() for permission checks
|   - guard_name isolation (central-api vs tenant-api)
|
| Tokens are issued without ability scoping. Route protection relies
| on Spatie permissions + auth guards, not Sanctum abilities.
|
| This file is retained as a reference for the resource-specific
| naming conventions that informed the central-permissions.php
| and tenant-permissions.php configs.
|
*/

return [];
