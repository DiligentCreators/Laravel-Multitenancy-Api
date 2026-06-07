<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait PaginatesRequestTrait
{
    public function perPage(Request $request): int
    {
        return min((int) $request->query('per_page', 10), 100);
    }
}
