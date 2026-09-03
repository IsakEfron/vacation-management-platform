<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Empleado;
use App\Models\Auditoria;

class PersonalController extends Controller
{

    public function index(Request $request)
    {
        // Precargar los 4 roles una sola vez (tabla estática, nunca cambia)
        $roles = \Illuminate\Support\Facades\Cache::remember(
            'catalogo_roles',
            3600, // 1 hora — los roles nunca cambian en producción
            fn() => \App\Models\Rol::pluck('tipo', 'id_rol')->toArray()
            // resultado: [1 => 'Empleado', 2 => 'Supervisor', 3 => 'Admin RH', 4 => 'SuperAdmin']
        );

        $soloActivos = $request->input('activo', '1');
        $orderBy     = in_array($request->input('order'), ['nombre', 'nomina', 'saldo'])
                        ? $request->input('order') : 'nombre';
        $direction   = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $query = Empleado::query(); // <- sin with('rolInfo'), ya no se necesita

        if ($soloActivos === '1')     $query->where('activo', 1);
        elseif ($soloActivos === '0') $query->where('activo', 0);
        if ($request->filled('rol') && $request->rol !== '')
            $query->where('rol', (int) $request->rol);
        if ($request->filled('buscar')) {
            $b = $request->buscar;
            $query->where(function($q) use ($b) {
                // Si el buscar es completamente numérico -> solo buscar en nómina (usa índice)
                if (ctype_digit($b)) {
                    $q->where('nomina', 'like', "{$b}%"); // <- sin % al inicio = usa índice
                } else {
                    // Si tiene letras -> solo buscar en nombre
                    $q->where('nombre', 'like', "%{$b}%");
                }
            });
        }

        $paginado = $query->orderBy($orderBy, $direction)->paginate(20);

        return response()->json([
            'data' => collect($paginado->items())->map(fn($e) => [
                'nomina'      => $e->nomina,
                'nombre'      => $e->nombre,
                'rol'         => (int) $e->rol,
                'rol_nombre'  => $roles[(int) $e->rol] ?? '—', // <- lookup directo sin query
                'saldo'       => $e->saldo,
                'activo'      => (bool) $e->activo,
                'centro_pago' => $e->centro_pago ?? '—',
            ]),
            'meta' => [
                'total'        => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page'    => $paginado->lastPage(),
                'per_page'     => $paginado->perPage(),
                'order'        => $orderBy,
                'dir'          => $direction,
            ],
        ]);
    }

    public function updateRol(Request $request, string $nomina)
    {
        $request->validate(['rol' => ['required', 'integer', 'between:1,4']]);
        $admin    = Auth::guard('empleado')->user();
        $empleado = Empleado::where('nomina', $nomina)->where('activo', 1)->firstOrFail();

        if ($nomina === $admin->nomina) return response()->json(['error' => 'No puedes cambiar tu propio rol.'], 422);
        if ((int)$admin->rol === 3 && (int)$empleado->rol >= 3) return response()->json(['error' => 'Sin permisos para modificar este usuario.'], 403);

        // Solo puede haber UN SuperAdmin en el sistema
        if ((int) $request->rol === 4) {
            $superadminsActivos = Empleado::where('rol', 4)->where('activo', 1)
                ->where('nomina', '!=', $nomina)->count();
            if ($superadminsActivos >= 1) {
                return response()->json([
                    'error' => 'Ya existe un SuperAdmin activo. Solo puede haber uno en el sistema.',
                ], 422);
            }
        }

        $rolAnterior = $empleado->rol;
        $empleado->update(['rol' => $request->rol]);
        Auditoria::registrar($admin->nomina, 'CAMBIO_ROL', "Nómina:{$nomina} Rol:{$rolAnterior}->{$request->rol}", $request->ip());
        return response()->json(['message' => 'Rol actualizado correctamente.']);
    }

    public function resetPassword(Request $request, string $nomina)
    {
        $request->validate([
            'new_password'              => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ]);

        $admin    = Auth::guard('empleado')->user();
        $empleado = Empleado::where('nomina', $nomina)->where('activo', 1)->firstOrFail();

        // Admin RH (3) no puede resetear contraseña de Admin RH ni SuperAdmin
        if ((int) $admin->rol === 3 && (int) $empleado->rol >= 3) {
            return response()->json([
                'error' => 'No tienes permisos para cambiar la contraseña de este usuario.',
            ], 403);
        }

        // Nadie puede resetear la contraseña del SuperAdmin excepto él mismo
        if ((int) $empleado->rol === 4 && $admin->nomina !== $nomina) {
            return response()->json([
                'error' => 'Solo el SuperAdmin puede cambiar su propia contraseña.',
            ], 403);
        }

        $empleado->update(['password' => Hash::make($request->new_password)]);
        Auditoria::registrar($admin->nomina, 'RESET_PASSWORD', "Nómina:{$nomina}", $request->ip());
        return response()->json(['message' => 'Contraseña restablecida correctamente.']);
    }

    public function desactivar(string $nomina)
    {
        $admin    = Auth::guard('empleado')->user();
        if ($nomina === $admin->nomina) return response()->json(['error' => 'No puedes desactivar tu propia cuenta.'], 422);

        $empleado    = Empleado::where('nomina', $nomina)->where('activo', 1)->firstOrFail();
        $empleadoRol = (int) $empleado->rol;

        if ((int)$admin->rol === 3 && $empleadoRol >= 3) return response()->json(['error' => 'Sin permisos para dar de baja a Administradores o SuperAdmins.'], 403);

        if ($empleadoRol === 4) {
            if (Empleado::where('rol', 4)->where('activo', 1)->count() <= 1) {
                return response()->json(['error' => 'No puedes dar de baja al único SuperAdmin activo.'], 422);
            }
        }

        $empleado->update(['activo' => 0]);
        Auditoria::registrar($admin->nomina, 'BAJA_EMPLEADO', "Nómina:{$nomina} Nombre:{$empleado->nombre}", request()->ip());
        return response()->json(['message' => "{$empleado->nombre} dado de baja correctamente."]);
    }

    // ── Reactivar empleado ────────────────────────────────────────
    public function reactivar(string $nomina)
    {
        $admin    = Auth::guard('empleado')->user();
        $empleado = Empleado::where('nomina', $nomina)->where('activo', 0)->firstOrFail();
        $empleado->update(['activo' => 1]);
        Auditoria::registrar($admin->nomina, 'REACTIVAR_EMPLEADO', "Nómina:{$nomina}", request()->ip());
        return response()->json(['message' => "{$empleado->nombre} reactivado correctamente."]);
    }

    public function hardDestroyEmpleado(string $nomina)
    {
        $admin = Auth::guard('empleado')->user();

        if ((int) $admin->rol !== 4) {
            return response()->json([
                'error' => 'Solo el SuperAdmin puede eliminar empleados permanentemente.',
            ], 403);
        }

        $empleado = Empleado::where('nomina', $nomina)
                            ->where('activo', 0)
                            ->firstOrFail();

        if ($nomina === $admin->nomina) {
            return response()->json(['error' => 'No puedes eliminarte a ti mismo.'], 422);
        }

        $reservasActivas = DB::table('reservas')
            ->where('id_empleado', $nomina)
            ->whereNull('deleted_at')
            ->whereNotIn('estado', [3, 5, 6])
            ->count();

        if ($reservasActivas > 0) {
            return response()->json([
                'error' => "No se puede eliminar: el empleado tiene {$reservasActivas} solicitud(es) activa(s). Cancélalas primero.",
            ], 422);
        }

        $esSupervidor = DB::table('grupos')
            ->where('supervisor', $nomina)
            ->exists();
            
        if ($esSupervidor) {
            return response()->json([
                'error' => 'No se puede eliminar: el empleado es supervisor de uno o más grupos activos. '
                        . 'Cambia el supervisor de los grupos primero.',
            ], 422);
        }


        $nombreGuardado = $empleado->nombre;
        $nominaGuardada = $empleado->nomina;

        DB::transaction(function () use ($empleado, $admin, $nombreGuardado, $nominaGuardada) {
            // 1. Historial de sus reservas
            $reservaIds = DB::table('reservas')
                ->where('id_empleado', $nominaGuardada)
                ->pluck('id_reserva');

            if ($reservaIds->isNotEmpty()) {
                DB::table('history')
                    ->whereIn('id_reserva', $reservaIds)
                    ->delete();
            }

            // 2. Sus reservas
            DB::table('reservas')
                ->where('id_empleado', $nominaGuardada)
                ->delete();

            // 3. Sus membresías de grupo
            DB::table('grupo_empleado')
                ->where('nomina', $nominaGuardada)
                ->delete();

            // 4. Sus intentos de login
            DB::table('login_intentos')
                ->where('nomina', $nominaGuardada)
                ->delete();

            // 5. El empleado
            $empleado->delete();

            Auditoria::registrar(
                $admin->nomina,
                'EMPLEADO_ELIMINADO_HARD',
                "Nómina:{$nominaGuardada} | Nombre:{$nombreGuardado}",
                request()->ip()
            );
        });

        Cache::forget('total_empleados_activos');

        return response()->json([
            'message' => "{$nombreGuardado} eliminado permanentemente de la base de datos.",
        ]);
    }


    // ── Usuarios bloqueados permanentemente ──────────────────────

    public function bloqueados()
    {
        $lista = Empleado::where('login_bloqueado', 1)
            ->orderBy('nombre')
            ->get()
            ->map(fn($e) => [
                'nomina'  => $e->nomina,
                'nombre'  => $e->nombre,
                'rol'     => (int) $e->rol,
                'centro'  => $e->centro_pago ?? '—',
            ]);

        return response()->json($lista);
    }

    public function desbloquearUsuario(string $nomina)
    {
        $admin    = Auth::guard('empleado')->user();
        $empleado = Empleado::where('nomina', $nomina)->firstOrFail();
        $empleado->update(['login_bloqueado' => 0]);

        Auditoria::registrar(
            $admin->nomina,
            'DESBLOQUEAR_USUARIO',
            "Nómina desbloqueada: {$nomina} | {$empleado->nombre}",
            request()->ip()
        );

        return response()->json(['message' => "{$empleado->nombre} desbloqueado correctamente."]);
    }

    // ── IPs bloqueadas ────────────────────────────────────────────

    public function ipsBloqueadas()
    {
        $ips = \Illuminate\Support\Facades\DB::table('login_intentos')
            ->whereNotNull('bloqueado_en')
            ->selectRaw('ip, COUNT(*) as intentos, MAX(bloqueado_en) as bloqueado_en, MAX(fecha) as ultimo_intento')
            ->groupBy('ip')
            ->orderByDesc('bloqueado_en')
            ->get()
            ->map(fn($r) => [
                'ip'            => $r->ip,
                'intentos'      => $r->intentos,
                'bloqueado_en'  => $r->bloqueado_en
                    ? \Carbon\Carbon::parse($r->bloqueado_en)->format('d M Y H:i')
                    : '—',
                'ultimo_intento'=> $r->ultimo_intento
                    ? \Carbon\Carbon::parse($r->ultimo_intento)->format('d M Y H:i')
                    : '—',
            ]);

        return response()->json($ips);
    }

    public function desbloquearIp(string $ip)
    {
        $admin = Auth::guard('empleado')->user();

        \Illuminate\Support\Facades\DB::table('login_intentos')
            ->where('ip', $ip)
            ->update(['bloqueado_en' => null]);

        Auditoria::registrar(
            $admin->nomina,
            'DESBLOQUEAR_IP',
            "IP desbloqueada: {$ip}",
            request()->ip()
        );

        return response()->json(['message' => "IP {$ip} desbloqueada correctamente."]);
    }

}