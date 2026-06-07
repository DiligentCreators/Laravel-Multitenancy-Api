<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use App\Traits\PaginatesRequestTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, PaginatesRequestTrait, ValidatesRequests;

    protected ApiResponseService $api;

    public function __construct(ApiResponseService $api)
    {
        $this->api = $api;
    }
}
