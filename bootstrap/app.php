<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\EnsureUserRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->prepend(HandleCors::class);
         $middleware->alias([
             'role' => EnsureUserRole::class,
         ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // Handle Unauthenticated exception for API routes
            if ($e instanceof \Illuminate\Auth\AuthenticationException && $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            // Handle Authorization exception for API routes
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException && $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        });
    })->create();
