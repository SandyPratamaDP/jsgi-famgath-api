<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // HandleCors is automatically included in Laravel 11's global middleware stack.
        // Behavior is controlled entirely by config/cors.php.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
