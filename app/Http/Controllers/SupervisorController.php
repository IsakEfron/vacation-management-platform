<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Reserva;
use App\Models\History;
use App\Models\Auditoria;
use App\Models\Empleado;

class SupervisorController extends Controller
{
    // ── Solicitudes del equipo ────────────────────────────────────

    public function solicitudesEquipo(Request $request)
    {
        $sup = Auth::guard('empleado')->user();

        $nominasEquipo = DB::table('grupo_empleado as ge')
            ->join('grupos as g', 'ge.id_grupo', '=', 'g.id_grupo')
            ->where('g.supervisor', $sup->nomina)
            ->pluck('ge.nomina')
            ->toArray();

        if (empty($nominasEquipo)) {
            return response()->json([]);
        }

        // <- Limitar a estados activos y máximo 100 más recientes
        // Un supervisor no necesita ver más de las últimas 100 solicitudes activas
        $reservas = DB::table('reservas as r')
            ->join('empleados as e',      'r.id_empleado', '=', 'e.nomina')
            ->join('estado as es',        'r.estado',      '=', 'es.id_estado')
            ->join('tipo_solicitud as ts', 'r.id_tipo',     '=', 'ts.id_tipo')
            ->whereNull('r.deleted_at')
            ->whereIn('r.id_empleado', $nominasEquipo)
            ->whereIn('r.estado', [1, 2, 3])
            ->select(
                'r.id_reserva', 'r.fecha_inicial', 'r.fecha_final',
                'r.dias_habiles', 'r.observaciones', 'r.estado', 'r.created_at',
                'e.nomina', 'e.nombre as nombre_empleado',
                'es.nombre as estado_nombre', 'es.color_badge',
                'ts.nombre as tipo_nombre'
            )
            ->orderBy('r.estado')
            ->orderByDesc('r.created_at')
            ->limit(200) // <- sin esto con 500 empleados carga todo
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id_reserva,
                'nombre_empleado' => $r->nombre_empleado,
                'nomina'          => $r->nomina,
                'fecha_inicio'    => $r->fecha_inicial,
                'fecha_fin'       => $r->fecha_final,
                'dias_habiles'    => $r->dias_habiles,
                'tipo'            => $r->tipo_nombre,
                'estado'          => (int) $r->estado,
                'estado_nombre'   => $r->estado_nombre,
                'color'           => $r->color_badge,
                'observaciones'   => $r->observaciones,
                'puede_actuar'    => (int) $r->estado === 1,
            ]);

        return response()->json($reservas);
    }

    // ── Evaluar: VoBo (2) o Rechazar (3) ─────────────────────────

    public function evaluar(Request $request, int $id)
    {
        $request->validate([
            'decision'      => ['required', 'in:2,3'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $sup = Auth::guard('empleado')->user();

        // <- Quitar el filtro de estado del findOrFail para dar un error legible
        $reserva = Reserva::whereNull('deleted_at')->findOrFail($id);

        // <- Validar estado DESPUÉS para dar mensaje claro
        if ((int) $reserva->estado !== 1) {
            return response()->json([
                'error' => 'Esta solicitud ya no está en estado Pendiente (estado actual: ' . $reserva->estado . '). Recarga la página.',
            ], 422);
        }

        // Verificar pertenencia al equipo
        $esDeEquipo = DB::table('grupo_empleado as ge')
            ->join('grupos as g', 'ge.id_grupo', '=', 'g.id_grupo')
            ->where('g.supervisor', $sup->nomina)
            ->where('ge.nomina', $reserva->id_empleado)
            ->exists();

        if (!$esDeEquipo) {
            return response()->json(['error' => 'Este empleado no pertenece a tu equipo.'], 403);
        }

        $decision       = (int) $request->decision;
        $estadoAnterior = (int) $reserva->estado;

        DB::transaction(function () use ($sup, $reserva, $decision, $estadoAnterior, $request) {
            // reserva.observaciones = nota original del empleado — NO se sobreescribe
            // El comentario del supervisor va SOLO a history.detalles_cambio
            $reserva->update([
                'estado'     => $decision,
                'updated_at' => now(),
            ]);

            $comentarioSup = trim($request->observaciones ?? '');
            $detalleHistory = match($decision) {
                2 => 'Visto Bueno otorgado por Supervisor — enviado a RH'
                    . ($comentarioSup ? " | Comentario: {$comentarioSup}" : ''),
                3 => 'Rechazada por Supervisor'
                    . ($comentarioSup ? ": {$comentarioSup}" : ' (sin motivo indicado)'),
                default => 'Estado actualizado por Supervisor',
            };

            History::create([
                'id_reserva'      => $reserva->id_reserva,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $decision,
                'modificado_por'  => $sup->nomina,
                'detalles_cambio' => $detalleHistory,
                'fecha_cambio'    => now(),
            ]);

            // Si rechaza y el tipo usaba saldo -> devolver días al empleado
            if ($decision === 3) {
                $tipo = DB::table('tipo_solicitud')->where('id_tipo', $reserva->id_tipo)->first();
                if ($tipo && $tipo->usa_saldo) {
                    Empleado::where('nomina', $reserva->id_empleado)
                            ->increment('saldo', $reserva->dias_habiles);
                }
            }

            Auditoria::registrar(
                $sup->nomina,
                $decision === 2 ? 'SUP_VISTO_BUENO' : 'SUP_RECHAZO',
                "Reserva #{$reserva->id_reserva} | Empleado: {$reserva->id_empleado}",
                request()->ip()
            );
        });

        $msg = $decision === 2
            ? 'Visto Bueno otorgado. La solicitud fue enviada a Recursos Humanos.'
            : 'Solicitud rechazada. El saldo fue devuelto si aplicaba.';

        return response()->json(['message' => $msg]);
    }

    // ── KPIs ──────────────────────────────────────────────────────

    public function kpis()
    {
        $sup = Auth::guard('empleado')->user();

        $nominasEquipo = DB::table('grupo_empleado as ge')
            ->join('grupos as g', 'ge.id_grupo', '=', 'g.id_grupo')
            ->where('g.supervisor', $sup->nomina)
            ->pluck('ge.nomina')
            ->toArray();

        $total     = count($nominasEquipo);
        $pendientes = $total ? Reserva::whereNull('deleted_at')->whereIn('id_empleado', $nominasEquipo)->where('estado', 1)->count() : 0;
        $enviadas   = $total ? Reserva::whereNull('deleted_at')->whereIn('id_empleado', $nominasEquipo)->where('estado', 2)->count() : 0;

        return response()->json([
            'pendientes'   => $pendientes,
            'enviadas_rh'  => $enviadas,
            'total_equipo' => $total,
        ]);
    }

    public function miGrupo()
    {
        $sup = Auth::guard('empleado')->user();

        // Obtener todos los empleados en los grupos donde este supervisor es líder
        $miembros = DB::table('grupo_empleado as ge')
            ->join('grupos as g',    'ge.id_grupo', '=', 'g.id_grupo')
            ->join('empleados as e', 'ge.nomina',   '=', 'e.nomina')
            ->where('g.supervisor', $sup->nomina)
            ->where('e.activo', 1)
            ->select(
                'e.nomina',
                'e.nombre',
                'e.saldo',
                'e.centro_pago',
            )
            ->orderBy('e.nombre')
            ->get();

        return response()->json($miembros);
    }
}