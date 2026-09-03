<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Middleware globales ────────────────────────────────────
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SanitizeInput::class,
        ]);

        // ── Aliases ────────────────────────────────────────────────
        $middleware->alias([
            'role'        => \App\Http\Middleware\RoleMiddleware::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,
            'timeout'     => \App\Http\Middleware\SessionTimeout::class,
            'primera_vez' => \App\Http\Middleware\ForzarCambioPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Devolver JSON en rutas /api/* para cualquier excepción
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {

                $status  = 500;
                $message = 'Error interno del servidor.';

                if ($e instanceof \Illuminate\Http\Exceptions\HttpException) {
                    $status  = $e->getStatusCode();
                    $message = $e->getMessage() ?: $message;
                }

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'error'  => 'Error de validación.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                if ($status === 500 && app()->environment('production')) {
                    $message = 'Error interno del servidor.';
                } elseif ($status === 500) {
                    $message = $e->getMessage();
                }

                return response()->json(['error' => $message], $status);
            }
        });
    })->create();