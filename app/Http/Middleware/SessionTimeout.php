<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionTimeout
{
    // Tiempo de inactividad permitido en segundos
    // 3600 = 1 hora | Para pruebas usa 60 o 120
    const TIMEOUT = 3600;

    // Rutas que NO deben resetear el timer (pings de fondo)
    const RUTAS_EXCLUIDAS = [
        'api/notificaciones/mantenimiento',
        'api/mantenimiento/estado',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('empleado')->check()) {
            return $next($request);
        }

        $emp = Auth::guard('empleado')->user();

        // SuperAdmin: nunca expira, pero sí actualizamos last_activity
        if ((int) $emp->rol === 4) {
            if (!$this->esRutaExcluida($request)) {
                $request->session()->put('last_activity', time());
            }
            return $next($request);
        }

        // ── Verificar inactividad ─────────────────────────────────
        $lastActivity = $request->session()->get('last_activity');

        // Si no tiene last_activity es porque acaba de iniciar sesión -> inicializar
        if (!$lastActivity) {
            $request->session()->put('last_activity', time());
            return $next($request);
        }

        $inactivo = time() - $lastActivity;

        if ($inactivo > self::TIMEOUT) {
            // Expiró por inactividad
            Auth::guard('empleado')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'    => 'Sesión expirada por inactividad.',
                    'expirada' => true,
                    'redirect' => '/',
                ], 401);
            }

            return redirect('/')
                ->with('error', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.');
        }

        // Solo actualizar last_activity en acciones reales del usuario
        // NO en pings de fondo (campana, polling, etc.)
        if (!$this->esRutaExcluida($request)) {
            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }

    private function esRutaExcluida(Request $request): bool
    {
        foreach (self::RUTAS_EXCLUIDAS as $ruta) {
            if ($request->is($ruta)) {
                return true;
            }
        }
        return false;
    }
}