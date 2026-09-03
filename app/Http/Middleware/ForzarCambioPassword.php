<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForzarCambioPassword
{
    // Rutas que se permiten aunque primera_vez = 1
    private const RUTAS_PERMITIDAS = [
        'logout',
        'password.change',
        // Notificaciones de mantenimiento (ping de fondo)
        'notificaciones.mant',
    ];

    public function handle(Request $request, Closure $next)
    {
        $emp = Auth::guard('empleado')->user();

        if (!$emp) {
            return $next($request);
        }

        // Si primera_vez = 1 y no está en una ruta permitida -> bloquear
        if ($emp->primera_vez && !$request->routeIs(...self::RUTAS_PERMITIDAS)) {
            // Para peticiones API (fetch) devolver JSON, no redirect
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'primera_vez' => true,
                    'error'       => 'Debes cambiar tu contraseña antes de continuar.',
                ], 403);
            }

            // Para peticiones de página: dejar pasar pero con flag en sesión
            // El JS del layout se encarga de abrir el modal automáticamente
            $request->session()->put('primera_vez', true);
        }

        return $next($request);
    }
}