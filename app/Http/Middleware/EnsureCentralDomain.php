<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', []);

        $host = $request->getHost();

        if (! in_array($host, $centralDomains)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
