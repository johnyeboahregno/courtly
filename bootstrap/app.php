<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Trust the Caddy reverse proxy in front of the app container so
        // request()->isSecure() reflects the original HTTPS request instead
        // of the plain-HTTP hop between Caddy and php-fpm. Safe as '*' here:
        // the app container has no public port mapping, so only Caddy can
        // ever reach it, meaning nothing else can spoof these headers.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();
