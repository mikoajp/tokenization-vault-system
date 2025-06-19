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

        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
            'api.access_control' => \App\Http\Middleware\VaultAccessControl::class,
            'api.rate_limit' => \App\Http\Middleware\RateLimiting::class,
            'api.request_id' => \App\Http\Middleware\RequestIdMiddleware::class,
        ]);

        $middleware->append([
            \App\Http\Middleware\RequestIdMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\TokenizationException $e, $request) {
            return $e->render($request);
        });

        $exceptions->render(function (\App\Exceptions\VaultException $e, $request) {
            return $e->render($request);
        });

        $exceptions->render(function (\App\Exceptions\EncryptionException $e, $request) {
            return $e->render($request);
        });
    })
    ->withCommands([
        \App\Console\Commands\VaultSystemSetup::class,
        \App\Console\Commands\VaultAuditSummary::class,
        \App\Console\Commands\VaultMaintenance::class,
    ])
    ->create();
