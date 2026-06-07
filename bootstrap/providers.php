<?php

use App\Providers\ApiResponseServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    ApiResponseServiceProvider::class,
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
    TenancyServiceProvider::class,

];
