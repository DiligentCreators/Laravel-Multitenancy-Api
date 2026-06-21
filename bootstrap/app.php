<?php

use App\Http\Middleware\CheckTenantUsage;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureCrmFeature;
use App\Http\Middleware\EnsureGuardMatches;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureTenantSubscription;
use App\Http\Middleware\InitializeTenancy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Central domain guard — blocks tenant-domain access
            'central.domain' => EnsureCentralDomain::class,

            // Guard-forcing middleware — ensures the correct auth guard
            'guard' => EnsureGuardMatches::class,

            // Flexible tenant resolution (domain → header → input)
            'tenancy' => InitializeTenancy::class,

            // Subscription enforcement
            'subscription' => EnsureTenantSubscription::class,

            // Plan feature gating — usage: 'feature:users', 'feature:reports'
            'feature' => EnsurePlanFeature::class,

            // CRM feature gate enforcement (crm_feature_definitions)
            'crm-feature' => EnsureCrmFeature::class,

            // Tenant usage enforcement
            'usage' => CheckTenantUsage::class,
        ]);

        // SPA
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: __('Unauthenticated.'),
                    'errors' => new stdClass,
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException
                    ? __('Record not found.')
                    : __('Resource not found.');

                return response()->json([
                    'status' => false,
                    'message' => $message,
                    'errors' => new stdClass,
                ], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => __('Access denied.'),
                    'errors' => new stdClass,
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => __('Too many requests. Please try again after a few minutes.'),
                    'errors' => new stdClass,
                ], 429);
            }
        });

    })->create();
