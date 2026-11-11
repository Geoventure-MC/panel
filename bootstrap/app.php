<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
        ]);

        // Endpoint télémétrie appelé par le launcher (pas de session/CSRF).
        $middleware->validateCsrfTokens(except: [
            'utils/telemetry',
            'utils/achievements/unlock',
            'utils/seasons/sync',
            'api/geoventure/season-archive', // alias de utils/seasons/sync (même jeton) : sans cela il répondait toujours 419
            'utils/scheduled-events/claim',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
