<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        // Solo aplica a usuarios autenticados
        if (!Auth::guard('empleado')->check()) {
            return $next($request);
        }

        $emp = Auth::guard('empleado')->user();

        // SuperAdmin (rol 4) puede operar siempre
        if ((int) $emp->rol === 4) {
            return $next($request);
        }

        // <- Cachear el estado de mantenimiento 30 segundos
        // Si se activa mantenimiento, el cache se invalida explícitamente
        $enMantenimiento = \Illuminate\Support\Facades\Cache::remember(
            'maintenance_mode_active',
            30, // segundos
            fn() => DB::table('mantenimientos')->where('estado', 2)->exists()
        );

        if ($enMantenimiento) {
            Auth::guard('empleado')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'            => 'El sistema está en mantenimiento.',
                    'en_mantenimiento' => true,
                ], 503);
            }
            return redirect('/')->with('error', 'El sistema está en mantenimiento. Por favor intenta más tarde.');
        }

        return $next($request);
    }
}