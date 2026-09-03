<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Reserva;
use App\Models\TipoSolicitud;
use App\Models\History;
use App\Models\Auditoria;
use Carbon\Carbon;

class ReservaController extends Controller
{
    // ── Listar solicitudes propias ────────────────────────────────

    public function misSolicitudes()
    {
        $emp = Auth::guard('empleado')->user();

        $reservas = Reserva::with(['estadoInfo', 'tipoInfo'])
            ->where('id_empleado', $emp->nomina)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id_reserva,
                'fecha_inicio'  => $r->fecha_inicial->format('d M Y'),
                'fecha_fin'     => $r->fecha_final->format('d M Y'),
                'regreso'       => Reserva::calcularRegreso($r->fecha_final, $emp->centro_pago)->format('d M Y'),
                'dias_habiles'  => $r->dias_habiles,
                'tipo'          => $r->tipoInfo->nombre ?? '—',
                'estado'        => $r->estadoInfo->nombre ?? '—',
                'color'         => $r->estadoInfo->color_badge ?? 'gray',
                'observaciones' => $r->observaciones,
                'fecha_creacion'=> $r->created_at->format('d M Y'),
            ]);

        return response()->json($reservas);
    }

    // ── Crear solicitud ───────────────────────────────────────────

    public function store(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicial' => ['required', 'date', 'after_or_equal:today'],
                'fecha_final'   => ['required', 'date', 'after_or_equal:fecha_inicial'],
                'id_tipo'       => ['required', 'integer', 'exists:tipo_solicitud,id_tipo'],
                'observaciones' => ['nullable', 'string', 'max:500'],
            ], [
                'fecha_inicial.required'       => 'La fecha de inicio es obligatoria.',
                'fecha_inicial.after_or_equal' => 'La fecha de inicio no puede ser en el pasado.',
                'fecha_final.required'         => 'La fecha de fin es obligatoria.',
                'fecha_final.after_or_equal'   => 'La fecha final debe ser igual o posterior al inicio.',
                'id_tipo.required'             => 'Selecciona el tipo de permiso.',
                'id_tipo.exists'               => 'El tipo de permiso seleccionado no es válido.',
                'observaciones.max'            => 'Las observaciones no pueden superar los 500 caracteres.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return response()->json(['error' => $firstError], 422);
        }

        $emp    = Auth::guard('empleado')->user();
        $inicio = Carbon::parse($request->fecha_inicial);
        $fin    = Carbon::parse($request->fecha_final);
        $tipo = TipoSolicitud::where('id_tipo', $request->id_tipo)
            ->where('activo', 1)  // <- agregar esta condición
            ->firstOrFail();

        $diasHabiles = Reserva::calcularDiasHabiles($inicio, $fin, $emp->centro_pago);

        // Validar saldo
        if ($tipo->usa_saldo && $emp->saldo < $diasHabiles) {
            return response()->json([
                'error' => "Saldo insuficiente. Tienes {$emp->saldo} día(s) disponible(s) pero solicitaste {$diasHabiles}.",
            ], 422);
        }

        // Verificar traslape
        $traslape = Reserva::where('id_empleado', $emp->nomina)
            ->whereNull('deleted_at')
            ->whereNotIn('estado', [6])
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha_inicial', [$inicio, $fin])
                  ->orWhereBetween('fecha_final',  [$inicio, $fin])
                  ->orWhere(fn($q2) =>
                      $q2->where('fecha_inicial', '<=', $inicio)
                         ->where('fecha_final',   '>=', $fin)
                  );
            })->exists();

        if ($traslape) {
            return response()->json(['error' => 'Ya tienes una solicitud activa en esas fechas.'], 422);
        }

        // Supervisores (rol ≥ 2) saltan directo a Visto Bueno
        $empRol        = (int) $emp->rol;
        $estadoInicial = $empRol >= 2 ? 2 : 1;
        $detalle       = $empRol >= 2
            ? 'Solicitud creada — Visto Bueno automático (Supervisor)'
            : 'Solicitud creada';

        DB::transaction(function () use ($emp, $inicio, $fin, $tipo, $diasHabiles, $request, $estadoInicial, $detalle) {
            $reserva = Reserva::create([
                'fecha_inicial' => $inicio->toDateString(),
                'fecha_final'   => $fin->toDateString(),
                'dias_habiles'  => $diasHabiles,
                'id_empleado'   => $emp->nomina,
                'id_tipo'       => $tipo->id_tipo,
                'estado'        => $estadoInicial,
                'observaciones' => $request->observaciones,
            ]);

            History::create([
                'id_reserva'      => $reserva->id_reserva,
                'estado_anterior' => null,
                'estado_nuevo'    => $estadoInicial,
                'modificado_por'  => $emp->nomina,
                'detalles_cambio' => $detalle,
                'fecha_cambio'    => now(),
            ]);

            if ($tipo->usa_saldo) {
                $emp->decrement('saldo', $diasHabiles);
            }

            Auditoria::registrar(
                $emp->nomina,
                'RESERVA_CREADA',
                "Solicitud #{$reserva->id_reserva} | Tipo: {$tipo->nombre} | Días: {$diasHabiles}",
                request()->ip()
            );
        });

        $msgExtra = $empRol >= 2
            ? ' Tu solicitud fue enviada directamente a RH para aprobación.'
            : '';

        return response()->json([
            'message'      => "Solicitud enviada correctamente.{$msgExtra}",
            'dias_habiles' => $diasHabiles,
            'nuevo_saldo'  => $emp->fresh()->saldo,
            'regreso'      => Reserva::calcularRegreso($fin, $emp->centro_pago)->format('d M Y'),
        ]);
    }

    // ── Cancelar solicitud propia ─────────────────────────────────

    public function cancelar(int $id)
    {
        $emp    = Auth::guard('empleado')->user();
        $empRol = (int) $emp->rol;

        $reserva = Reserva::where('id_reserva', $id)
                          ->where('id_empleado', $emp->nomina)
                          ->whereNull('deleted_at')
                          ->firstOrFail();

        $estadoCancelable = $empRol >= 2 ? [1, 2] : [1];

        if (!in_array((int) $reserva->estado, $estadoCancelable)) {
            return response()->json([
                'error' => 'Solo puedes cancelar solicitudes en estado Pendiente' . ($empRol >= 2 ? ' o Visto Bueno.' : '.'),
            ], 422);
        }

        DB::transaction(function () use ($emp, $reserva) {
            $estadoAnterior = $reserva->estado;

            $reserva->update(['estado' => 6, 'deleted_at' => now()]);

            History::create([
                'id_reserva'      => $reserva->id_reserva,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => 6,
                'modificado_por'  => $emp->nomina,
                'detalles_cambio' => 'Cancelada por el solicitante',
                'fecha_cambio'    => now(),
            ]);

            $tipo = TipoSolicitud::find($reserva->id_tipo);
            if ($tipo && $tipo->usa_saldo && $reserva->dias_habiles > 0) {
                $emp->increment('saldo', $reserva->dias_habiles);
            }
        });

        return response()->json([
            'message'     => 'Solicitud cancelada.',
            'nuevo_saldo' => $emp->fresh()->saldo,
        ]);
    }

    // ── Preview de fechas (sin guardar) ───────────────────────────

    public function calcularFechas(Request $request)
    {
        $request->validate([
            'fecha_inicial' => ['required', 'date'],
            'fecha_final'   => ['required', 'date', 'after_or_equal:fecha_inicial'],
        ], [
            'fecha_final.after_or_equal' => 'La fecha final debe ser igual o posterior al inicio.',
        ]);

        $emp    = Auth::guard('empleado')->user();
        $inicio = Carbon::parse($request->fecha_inicial);
        $fin    = Carbon::parse($request->fecha_final);

        $diasHabiles = Reserva::calcularDiasHabiles($inicio, $fin, $emp->centro_pago);
        $regreso     = Reserva::calcularRegreso($fin, $emp->centro_pago);

        return response()->json([
            'dias_habiles' => $diasHabiles,
            'regreso'      => $regreso->format('d M Y'),
            'regreso_iso'  => $regreso->toDateString(),
        ]);
    }

    // ── Historial de una reserva (accesible roles 1-4) ───────────
    // El empleado solo puede ver su propio historial
    // Supervisores y admins pueden ver cualquiera

    public function historial(int $id)
    {
        $emp    = Auth::guard('empleado')->user();
        $empRol = (int) $emp->rol;

        // Rol 1 (empleado): solo ve su propia reserva
        // Rol 2 (supervisor): ve su propia O las de su equipo
        // Roles 3-4: ven cualquier reserva
        if ($empRol === 1) {
            $reserva = Reserva::where('id_reserva', $id)
                              ->where('id_empleado', $emp->nomina)
                              ->first();
            if (!$reserva) {
                return response()->json(['error' => 'No autorizado.'], 403);
            }
        } elseif ($empRol === 2) {
            // Supervisor: puede ver la suya O la de cualquier miembro de sus grupos
            $reserva = Reserva::where('id_reserva', $id)->first();
            if (!$reserva) {
                return response()->json(['error' => 'Solicitud no encontrada.'], 404);
            }
            $esSuya    = $reserva->id_empleado === $emp->nomina;
            $esEquipo  = DB::table('grupo_empleado as ge')
                ->join('grupos as g', 'ge.id_grupo', '=', 'g.id_grupo')
                ->where('g.supervisor', $emp->nomina)
                ->where('ge.nomina', $reserva->id_empleado)
                ->exists();
            if (!$esSuya && !$esEquipo) {
                return response()->json(['error' => 'No autorizado.'], 403);
            }
        }

        $items = DB::table('history as h')
            ->leftJoin('estado as ea', 'h.estado_anterior', '=', 'ea.id_estado')
            ->join('estado as en',     'h.estado_nuevo',    '=', 'en.id_estado')
            ->leftJoin('empleados as e', 'h.modificado_por', '=', 'e.nomina')
            ->where('h.id_reserva', $id)
            ->select(
                'h.id_history',
                'h.fecha_cambio',
                'h.detalles_cambio',
                'ea.nombre as estado_anterior',
                'en.nombre as estado_nuevo',
                'e.nombre as modificado_por_nombre',
                'h.modificado_por as modificado_por_nomina'
            )
            ->orderByDesc('h.fecha_cambio')
            ->get()
            ->map(fn($h) => [
                'id'              => $h->id_history,
                'fecha'           => \Carbon\Carbon::parse($h->fecha_cambio)->format('d M Y H:i'),
                'estado_anterior' => $h->estado_anterior ?? 'Creación',
                'estado_nuevo'    => $h->estado_nuevo,
                'modificado_por'  => $h->modificado_por_nombre ?? $h->modificado_por_nomina,
                'detalles'        => $h->detalles_cambio,
            ]);

        return response()->json($items);
    }


    // ── Calcular estado visual según fechas (solo para Aprobadas) ────────────
    // No altera el estado real en BD, es solo un badge adicional en la UI
    // Aprobada + hoy entre fechas   -> 'En curso'
    // Aprobada + hoy > fecha_final  -> 'Completada'
    // Aprobada + hoy < fecha_inicio -> 'Aprobada' (próxima)

    private static function estadoVisual(int $estado, $fechaInicio, $fechaFin): array
    {
        if ($estado !== 4) {
            return ['visual' => null, 'visual_color' => null];
        }

        $hoy    = now()->startOfDay();
        $inicio = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
        $fin    = \Carbon\Carbon::parse($fechaFin)->endOfDay();

        if ($hoy->between($inicio, $fin)) {
            return ['visual' => 'En curso', 'visual_color' => 'teal'];
        }
        if ($hoy->gt($fin)) {
            return ['visual' => 'Completada', 'visual_color' => 'indigo'];
        }
        return ['visual' => null, 'visual_color' => null]; // próxima, badge normal
    }

    // ── Catálogo de tipos de solicitud activos ────────────────────
    // Endpoint público para el select del formulario de solicitud.
    // Cache de 1 hora — se invalida desde TipoSolicitudController@store/update/toggle
    // Agrupa por con_goce para renderizar optgroups en el frontend.

    public function tiposCatalogo()
    {
        $cacheKey = 'catalogo_tipos_solicitud';
 
        try {
            $tipos = Cache::remember($cacheKey, 3600, function () {
                // ── Verificar si la columna 'activo' existe en tipo_solicitud ──────
                // SQL Server: usar INFORMATION_SCHEMA para no asumir el schema
                $tieneColumnaActivo = DB::selectOne("
                    SELECT 1 AS tiene
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME  = 'tipo_solicitud'
                      AND COLUMN_NAME = 'activo'
                ");
 
                $query = DB::table('tipo_solicitud');
 
                // Solo filtrar por activo=1 si la columna existe
                if ($tieneColumnaActivo) {
                    $query->where('activo', 1);
                }
 
                $rows = $query
                    // Orden: Vacaciones primero (usa_saldo=1 AND con_goce=1),
                    //        luego Con Goce, luego Sin Goce
                    ->orderByRaw('
                        CASE
                            WHEN usa_saldo = 1 AND con_goce = 1 THEN 1
                            WHEN con_goce  = 1                  THEN 2
                            ELSE                                     3
                        END
                    ')
                    ->orderBy('id_tipo')
                    ->select('id_tipo', 'nombre', 'con_goce', 'usa_saldo')
                    ->get();
 
                if ($rows->isEmpty()) {
                    \Illuminate\Support\Facades\Log::warning(
                        'tiposCatalogo: la tabla tipo_solicitud está vacía o todos los tipos están desactivados.'
                    );
                    return [];
                }
 
                return $rows->map(function ($t) {
                    $conGoce  = (bool) $t->con_goce;
                    $usaSaldo = (bool) $t->usa_saldo;
 
                    return [
                        'id'        => $t->id_tipo,
                        'nombre'    => $t->nombre,
                        'con_goce'  => $conGoce,
                        'usa_saldo' => $usaSaldo,
                        // Grupo para optgroup en el frontend
                        'grupo'     => match (true) {
                            $usaSaldo && $conGoce => 'Vacaciones',
                            $conGoce              => 'Con Goce de Sueldo',
                            default               => 'Sin Goce de Sueldo',
                        },
                    ];
                })->values()->all();
            });
 
            return response()->json($tipos);
 
        } catch (\Exception $e) {
            // Si el cache está corrupto o la consulta falla, limpiar y reintentar una vez
            Cache::forget($cacheKey);
 
            \Illuminate\Support\Facades\Log::error(
                'tiposCatalogo: error al cargar tipos. ' . $e->getMessage()
            );
 
            // Devolver 200 con array vacío — el JS mostrará el fallback
            // en lugar de un 500 que rompe la página
            return response()->json([], 200);
        }
    }
}