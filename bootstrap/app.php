<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\InternalMasterPortalAuth;
use App\Http\Middleware\InternalReleasePortalAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ForceJsonResponse::class);
        $middleware->alias([
            'internal.release.portal' => InternalReleasePortalAuth::class,
            'internal.master.portal' => InternalMasterPortalAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
