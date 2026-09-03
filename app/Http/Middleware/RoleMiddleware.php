<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Uso en rutas:
 *   ->middleware('role:3')      -> nivel mínimo 3 (Admin RH o superior)
 *   ->middleware('role:2,3')    -> nivel 2 o 3 exactamente
 *   ->middleware('role.min:2')  -> nivel mínimo 2 (Supervisor, Admin, SuperAdmin)
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $empleado = Auth::guard('empleado')->user();

        if (!$empleado) {
            return redirect()->route('login');
        }

        // Si no está activo, cerrar sesión
        if (!$empleado->activo) {
            Auth::guard('empleado')->logout();
            return redirect()->route('login')->withErrors(['usuario' => 'Tu cuenta ha sido desactivada.']);
        }

        // Verificar que el rol del empleado esté entre los permitidos
        if (!empty($roles) && !in_array((string) $empleado->rol, $roles)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}