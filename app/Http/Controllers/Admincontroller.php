<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Reserva;
use App\Models\Estado;
use App\Models\History;
use App\Models\Auditoria;
use App\Models\Empleado;
use Illuminate\Support\Facades\Cache; 

class AdminController extends Controller
{
    // ── Helper: construir la clave de cache de KPIs ───────────────────────────
    // La clave incluye el rango y las fechas para que cada combinación
    // tenga su propio cache independiente.

    private function kpiCacheKey(string $rango, ?array $fechaQ): string
    {
        return 'admin_kpis_' . $rango . '_' . md5(json_encode($fechaQ));
    }

    // ── Invalidar todos los caches de KPIs ────────────────────────────────────
    // Llamar después de cualquier cambio en reservas (update, destroy, hardDestroy)

    private function invalidarCacheKpis(): void
    {
        $hoy = now();

        // Reconstruir exactamente las mismas fechas que genera kpis()
        $rangosConFechas = [
            'todo'      => null,
            'semana'    => [$hoy->copy()->startOfWeek()->format('Y-m-d H:i:s'),
                            $hoy->copy()->endOfWeek()->format('Y-m-d H:i:s')],
            'mes'       => [$hoy->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                            $hoy->copy()->endOfMonth()->format('Y-m-d H:i:s')],
            'año'       => [$hoy->copy()->startOfYear()->format('Y-m-d H:i:s'),
                            $hoy->copy()->endOfYear()->format('Y-m-d H:i:s')],
            'quincena'  => $hoy->day <= 15
                ? [$hoy->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                $hoy->copy()->startOfMonth()->addDays(14)->endOfDay()->format('Y-m-d H:i:s')]
                : [$hoy->copy()->startOfMonth()->addDays(15)->format('Y-m-d H:i:s'),
                $hoy->copy()->endOfMonth()->format('Y-m-d H:i:s')],
        ];

        foreach ($rangosConFechas as $rango => $fechas) {
            Cache::forget('admin_kpis_' . $rango . '_' . md5(json_encode($fechas)));
        }
    }

    // ── KPIs del dashboard ────────────────────────────────────────────────────
    public function kpis(Request $request)
    {
        $rango  = $request->input('rango', 'todo');
        $fechaQ = null;
        $hoy    = now();

        switch ($rango) {
            case 'semana':
                $fechaQ = [
                    $hoy->copy()->startOfWeek()->format('Y-m-d H:i:s'),
                    $hoy->copy()->endOfWeek()->format('Y-m-d H:i:s'),
                ];
                break;
            case 'quincena':
                $dia = $hoy->day;
                $fechaQ = $dia <= 15
                    ? [
                        $hoy->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                        $hoy->copy()->startOfMonth()->addDays(14)->endOfDay()->format('Y-m-d H:i:s'),
                    ]
                    : [
                        $hoy->copy()->startOfMonth()->addDays(15)->format('Y-m-d H:i:s'),
                        $hoy->copy()->endOfMonth()->format('Y-m-d H:i:s'),
                    ];
                break;
            case 'mes':
                $fechaQ = [
                    $hoy->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                    $hoy->copy()->endOfMonth()->format('Y-m-d H:i:s'),
                ];
                break;
            case 'año':
                $fechaQ = [
                    $hoy->copy()->startOfYear()->format('Y-m-d H:i:s'),
                    $hoy->copy()->endOfYear()->format('Y-m-d H:i:s'),
                ];
                break;
            case 'personalizado':
                $desde = $request->input('desde');
                $hasta = $request->input('hasta');
                if ($desde && $hasta) {
                    $fechaQ = [
                        Carbon::parse($desde)->startOfDay()->format('Y-m-d H:i:s'),
                        Carbon::parse($hasta)->endOfDay()->format('Y-m-d H:i:s'),
                    ];
                }
                break;
        }

        $cacheKey = $this->kpiCacheKey($rango, $fechaQ);

        try {
            $stats = Cache::remember($cacheKey, 60, function () use ($fechaQ) {
                $row = DB::table('reservas')
                    ->whereNull('deleted_at')
                    ->when($fechaQ, fn($q) => $q->whereBetween('created_at', $fechaQ))
                    ->selectRaw("
                        ISNULL(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) as pendientes,
                        ISNULL(SUM(CASE WHEN estado = 2 THEN 1 ELSE 0 END), 0) as visto_bueno,
                        ISNULL(SUM(CASE WHEN estado = 4 THEN 1 ELSE 0 END), 0) as aprobadas,
                        ISNULL(SUM(CASE WHEN estado IN (3,5) THEN 1 ELSE 0 END), 0) as rechazadas,
                        ISNULL(SUM(CASE WHEN estado = 6 THEN 1 ELSE 0 END), 0) as canceladas
                    ")
                    ->first();
            // Cachear array de primitivos, NO el objeto stdClass
            // El stdClass de SQL Server a veces no se serializa bien con el driver de BD
            return [
                'pendientes'  => (int) ($row->pendientes  ?? 0),
                'visto_bueno' => (int) ($row->visto_bueno ?? 0),
                'aprobadas'   => (int) ($row->aprobadas   ?? 0),
                'rechazadas'  => (int) ($row->rechazadas  ?? 0),
                'canceladas'  => (int) ($row->canceladas  ?? 0),
            ];
        });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('KPIs cache falló: ' . $e->getMessage());
            $row = DB::table('reservas')
                ->whereNull('deleted_at')
                ->when($fechaQ, fn($q) => $q->whereBetween('created_at', $fechaQ))
                ->selectRaw("
                    ISNULL(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) as pendientes,
                    ISNULL(SUM(CASE WHEN estado = 2 THEN 1 ELSE 0 END), 0) as visto_bueno,
                    ISNULL(SUM(CASE WHEN estado = 4 THEN 1 ELSE 0 END), 0) as aprobadas,
                    ISNULL(SUM(CASE WHEN estado IN (3,5) THEN 1 ELSE 0 END), 0) as rechazadas,
                    ISNULL(SUM(CASE WHEN estado = 6 THEN 1 ELSE 0 END), 0) as canceladas
                ")
                ->first();
            $stats = [
                'pendientes'  => (int) ($row->pendientes  ?? 0),
                'visto_bueno' => (int) ($row->visto_bueno ?? 0),
                'aprobadas'   => (int) ($row->aprobadas   ?? 0),
                'rechazadas'  => (int) ($row->rechazadas  ?? 0),
                'canceladas'  => (int) ($row->canceladas  ?? 0),
            ];
        }

        try {
            $totalEmpleados = Cache::remember('total_empleados_activos', 120, function () {
                return (int) DB::table('empleados')->where('activo', 1)->count();
            });
        } catch (\Exception $e) {
            $totalEmpleados = (int) DB::table('empleados')->where('activo', 1)->count();
        }

        return response()->json([
            'pendientes'      => $stats['pendientes'],
            'visto_bueno'     => $stats['visto_bueno'],
            'aprobadas'       => $stats['aprobadas'],
            'rechazadas'      => $stats['rechazadas'],
            'canceladas'      => $stats['canceladas'],
            'total_empleados' => $totalEmpleados,
            'rango_label'     => $this->rangoLabel($rango, $fechaQ, $request),
        ]);
    }

    // ── Actualizar estado de una reserva ─────────────────────────────────────

    public function update(Request $request, int $id)
    {
        $request->validate([
            'estado'        => ['required', 'integer', 'between:1,6'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $admin   = Auth::guard('empleado')->user();
        $reserva = Reserva::whereNull('deleted_at')->findOrFail($id);

        $estadoAnterior = $reserva->estado;
        $estadoNuevo    = $request->estado;

        if ($estadoAnterior === $estadoNuevo && !$request->filled('observaciones')) {
            return response()->json(['error' => 'No hay cambios que guardar.'], 422);
        }

        DB::transaction(function () use ($admin, $reserva, $estadoAnterior, $estadoNuevo, $request) {
            $reserva->update(['estado' => $estadoNuevo, 'updated_at' => now()]);

            $comentarioRH = trim($request->observaciones ?? '');

            if ($estadoAnterior !== $estadoNuevo) {
                History::create([
                    'id_reserva'      => $reserva->id_reserva,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo'    => $estadoNuevo,
                    'modificado_por'  => $admin->nomina,
                    'detalles_cambio' => $comentarioRH ?: 'Cambio de estado por Admin RH',
                    'fecha_cambio'    => now(),
                ]);

                if (in_array($estadoNuevo, [3, 5, 6])) {
                    $tipo = $reserva->tipoInfo;
                    if ($tipo && $tipo->usa_saldo && $reserva->dias_habiles > 0) {
                        Empleado::where('nomina', $reserva->id_empleado)
                            ->increment('saldo', $reserva->dias_habiles);
                    }
                }
            }

            Auditoria::registrar(
                $admin->nomina,
                'RESERVA_ACTUALIZADA',
                "ID #{$reserva->id_reserva} | Estado: {$estadoAnterior} -> {$estadoNuevo}",
                request()->ip()
            );
        });

        // <- Invalidar cache de KPIs después de cambiar una reserva
        $this->invalidarCacheKpis();

        return response()->json(['message' => 'Solicitud actualizada correctamente.']);
    }

    // ── Eliminar soft ─────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $admin   = Auth::guard('empleado')->user();
        $reserva = Reserva::whereNull('deleted_at')->findOrFail($id);

        DB::transaction(function () use ($admin, $reserva) {
            $estadoAnterior = $reserva->estado;

            $reserva->update(['estado' => 6, 'deleted_at' => now(), 'updated_at' => now()]);

            History::create([
                'id_reserva'      => $reserva->id_reserva,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => 6,
                'modificado_por'  => $admin->nomina,
                'detalles_cambio' => 'Cancelada (soft) por administrador',
                'fecha_cambio'    => now(),
            ]);

            $tipo = $reserva->tipoInfo;
            if ($tipo && $tipo->usa_saldo && $reserva->dias_habiles > 0
                && !in_array($estadoAnterior, [3, 5, 6])) {
                Empleado::where('nomina', $reserva->id_empleado)
                    ->increment('saldo', $reserva->dias_habiles);
            }

            Auditoria::registrar($admin->nomina, 'RESERVA_ELIMINADA',
                "Reserva #{$reserva->id_reserva}", request()->ip());
        });

        // <- Invalidar cache
        $this->invalidarCacheKpis();

        return response()->json(['message' => 'Solicitud eliminada.']);
    }

    // ── Hard destroy ──────────────────────────────────────────────────────────

    public function hardDestroy(int $id)
    {
        $admin = Auth::guard('empleado')->user();

        if ((int) $admin->rol !== 4) {
            return response()->json(['error' => 'Solo el SuperAdmin puede eliminar permanentemente.'], 403);
        }

        $reserva = Reserva::withTrashed()->findOrFail($id);

        DB::transaction(function () use ($reserva, $admin) {
            $empleadoNombre = DB::table('empleados')->where('nomina', $reserva->id_empleado)->value('nombre') ?? $reserva->id_empleado;
            $tipoNombre     = DB::table('tipo_solicitud')->where('id_tipo', $reserva->id_tipo)->value('nombre') ?? "Tipo #{$reserva->id_tipo}";
            $estadoNombre   = DB::table('estado')->where('id_estado', $reserva->estado)->value('nombre') ?? "Estado #{$reserva->estado}";

            $detalle = implode(' | ', [
                "ID #{$reserva->id_reserva}",
                "Empleado: {$empleadoNombre} ({$reserva->id_empleado})",
                "Periodo: {$reserva->fecha_inicial} al {$reserva->fecha_final}",
                "Días: {$reserva->dias_habiles}",
                "Tipo: {$tipoNombre}",
                "Estado al eliminar: {$estadoNombre}",
            ]);

            DB::table('history')->where('id_reserva', $reserva->id_reserva)->delete();
            $reserva->forceDelete();

            Auditoria::registrar($admin->nomina, 'RESERVA_ELIMINADA_HARD', $detalle, request()->ip());
        });

        // <- Invalidar cache
        $this->invalidarCacheKpis();

        return response()->json(['message' => 'Solicitud eliminada permanentemente.']);
    }

    // ── También invalidar cuando se importa Excel (cambia total_empleados) ────
    // Llamar esto desde MantenimientoController después de importarExcel exitoso:
    // Cache::forget('total_empleados_activos');

    private function rangoLabel(string $rango, ?array $fechaQ, Request $request): string
    {
        return match($rango) {
            'semana'        => 'Esta semana',
            'quincena'      => 'Esta quincena',
            'mes'           => now()->locale('es')->isoFormat('MMMM YYYY'),
            'año'           => 'Año ' . now()->year,
            'personalizado' => $request->input('desde', '—') . ' al ' . $request->input('hasta', '—'),
            default         => 'Acumulado total',
        };
    }

    // ── Listar reservas paginadas ─────────────────────────────────

    public function index(Request $request)
    {
        $estado = $request->input('estado');
        $buscar = $request->input('buscar');

        $query = Reserva::with([
            'empleado:nomina,nombre,centro_pago',
            'estadoInfo:id_estado,nombre,color_badge',
            'tipoInfo:id_tipo,nombre,con_goce,usa_saldo',
        ])->whereNull('reservas.deleted_at');

        if ($estado) $query->where('estado', $estado);
        if ($buscar) {
            $query->join('empleados as eb', 'reservas.id_empleado', '=', 'eb.nomina')
                ->where(fn($q) =>
                    $q->where('eb.nomina', 'like', "%{$buscar}%")
                        ->orWhere('eb.nombre', 'like', "%{$buscar}%")
                );
        }

        // AdminController::index — agregar validación del per_page
        $perPage = min((int) $request->input('per_page', 15), 50); // máximo 50
        $paginated = $query->paginate($perPage);

        $data = $paginated->getCollection()->map(fn($r) => [
            'id'           => $r->id_reserva,
            'nombre'       => $r->empleado->nombre ?? '—',
            'nomina'       => $r->id_empleado,
            'iniciales'    => $this->iniciales($r->empleado->nombre ?? '?'),
            'fecha_inicio' => $r->fecha_inicial->format('d M Y'),
            'fecha_fin'    => $r->fecha_final->format('d M Y'),
            'dias_habiles' => $r->dias_habiles,
            'tipo'         => $r->tipoInfo->nombre ?? '—',
            'estado'       => $r->estadoInfo->nombre ?? '—',
            'estado_id'    => $r->estado,
            'color'        => $r->estadoInfo->color_badge ?? 'gray',
            'observaciones'=> $r->observaciones,
        ]);

        return response()->json([
            'data'         => $data,
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'from'         => $paginated->firstItem(),
            'to'           => $paginated->lastItem(),
        ]);
    }


    // ── Historial de cambios de una reserva ──────────────────────

    public function historial(int $id)
    {
        $items = DB::table('history as h')
            ->leftJoin('estado as ea', 'h.estado_anterior', '=', 'ea.id_estado')
            ->join('estado as en',     'h.estado_nuevo',    '=', 'en.id_estado')
            ->leftJoin('empleados as e', 'h.modificado_por', '=', 'e.nomina')
            ->where('h.id_reserva', $id)
            ->select(
                'h.id_history', 'h.fecha_cambio', 'h.detalles_cambio',
                'ea.nombre as estado_anterior',
                'en.nombre as estado_nuevo',
                'e.nombre as modificado_por_nombre',
                'h.modificado_por as modificado_por_nomina'
            )
            ->orderByDesc('h.fecha_cambio')
            ->get()
            ->map(fn($h) => [
                'id'              => $h->id_history,
                'fecha'           => Carbon::parse($h->fecha_cambio)->format('d M Y H:i'),
                'estado_anterior' => $h->estado_anterior ?? 'Creación',
                'estado_nuevo'    => $h->estado_nuevo,
                'modificado_por'  => $h->modificado_por_nombre ?? $h->modificado_por_nomina,
                'detalles'        => $h->detalles_cambio,
            ]);

        return response()->json($items);
    }


    public function exportar(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $estado = $request->input('estado');
        $buscar = $request->filled('buscar') ? substr($request->input('buscar'), 0, 100) : null;


        // ── CRÍTICO: declarar ANTES de usar en Cache::remember ────────────────
        $quincenaManual = $request->input('quincena') ? (int) $request->input('quincena') : null;
        $anioManual     = $request->input('anio')     ? (int) $request->input('anio')     : now()->year;

        // ── Cargar quincenas del año desde BD (cache 1 hora) ──────────────────
        // Si no hay quincenas en BD el array queda vacío y se usa el fallback manual
        $quincenasPorFecha = Cache::remember(
            "quincenas_anio_{$anioManual}",
            3600,
            function () use ($anioManual) {
                $rows = DB::table('quincenas')
                    ->where('anio', $anioManual)
                    ->where('activo', 1)
                    ->orderBy('numero')
                    ->get()
                    ->map(fn($q) => [
                        'numero'       => (int) $q->numero,
                        'fecha_inicio' => $q->fecha_inicio,
                        'fecha_fin'    => $q->fecha_fin,
                    ])
                    ->toArray();
        
                // No cachear si está vacío — las quincenas pueden registrarse después
                if (empty($rows)) {
                    Cache::forget("quincenas_anio_{$anioManual}");
                    return [];
                }
        
                return $rows;
            }
        );


        // ── Función de cálculo de periodo por tipo de nómina ──────────────────
        // Para quincenal (tipo 3): busca en BD por fecha. Fallback = número manual o fórmula.
        // Para semanal   (tipo 1): semana ISO del año (siempre automático).
        $calcularPeriodo = function (int $tipoNomina, string $fechaInicial)
            use ($quincenaManual, $quincenasPorFecha): int {

            if ($tipoNomina === 1) {
                return (int) \Carbon\Carbon::parse($fechaInicial)->format('W');
            }

            if ($tipoNomina === 3) {
                // Buscar en las quincenas cargadas de BD
                foreach ($quincenasPorFecha as $q) {
                    if ($fechaInicial >= $q['fecha_inicio'] && $fechaInicial <= $q['fecha_fin']) {
                        return $q['numero'];
                    }
                }
                // Fallback 1: número ingresado manualmente en el modal
                if ($quincenaManual) {
                    return $quincenaManual;
                }
                // Fallback 2: fórmula estándar (1-15 = impar, 16-fin = par)
                $fecha = \Carbon\Carbon::parse($fechaInicial);
                return ($fecha->month - 1) * 2 + ($fecha->day <= 15 ? 1 : 2);
            }

            return 0;
        };

        // ── Query base ────────────────────────────────────────────────────────
        $query = DB::table('reservas as r')
            ->join('empleados as e',      'r.id_empleado', '=', 'e.nomina')
            ->join('estado as es',        'r.estado',      '=', 'es.id_estado')
            ->join('tipo_solicitud as ts', 'r.id_tipo',    '=', 'ts.id_tipo')
            ->whereNull('r.deleted_at')
            ->select(
                'e.nomina', 'e.nombre', 'e.centro_pago', 'e.tipo_nomina',
                'r.fecha_inicial', 'r.fecha_final', 'r.dias_habiles',
                'ts.nombre as tipo_permiso', 'es.nombre as estado_nombre',
                'r.observaciones', 'r.created_at'
            )
            ->orderByDesc('r.created_at');

        if ($estado) $query->where('r.estado', $estado);
        if ($buscar) {
            $query->where(fn($q) =>
                $q->where('e.nomina', 'like', "%{$buscar}%")
                  ->orWhere('e.nombre', 'like', "%{$buscar}%")
            );
        }

        // ── Crear Spreadsheet ─────────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Hoja 1 — Reporte RH
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Reporte RH');

        $headers1 = [
            'A' => 'Nómina',       'B' => 'Nombre',         'C' => 'Centro de Pago',
            'D' => 'Fecha Inicio', 'E' => 'Fecha Fin',       'F' => 'Días Hábiles',
            'G' => 'Tipo Permiso', 'H' => 'Estado',          'I' => 'Observaciones',
            'J' => 'Fecha Solicitud',
        ];
        foreach ($headers1 as $col => $label) {
            $sheet1->setCellValue("{$col}1", $label);
        }
        $sheet1->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet1->getStyle('A1:J1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E3A5F');
        $sheet1->getStyle('A1:J1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet1->freezePane('A2');

        // Hoja 2 — TREESS-ASCII
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('TREESS-ASCII');

        $headers2 = [
            'A' => 'Nómina',        'B' => 'Fecha Inicio',  'C' => 'Fecha Fin',
            'D' => 'Días a Pagar',  'E' => 'Días Prima Vac','F' => '',
            'G' => 'Año Nómina',    'H' => 'Tipo Nómina',   'I' => 'Periodo Nómina',
            'J' => 'Observaciones', 'K' => 'ASCII',
        ];
        foreach ($headers2 as $col => $label) {
            $sheet2->setCellValue("{$col}1", $label);
        }
        $sheet2->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:K1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E3A5F');
        $sheet2->getStyle('A1:K1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet2->freezePane('A2');

        // ── Llenar con chunk ──────────────────────────────────────────────────
        $row       = 2;
        $anioActual = $anioManual;
        $totalFilas = 0;

        $query->chunk(500, function ($filas) use (
            $sheet1, $sheet2, &$row, $anioActual, $calcularPeriodo, &$totalFilas
        ) {
            foreach ($filas as $r) {
                $fechaIni = $r->fecha_inicial
                    ? \Carbon\Carbon::parse($r->fecha_inicial)->format('d/m/Y') : '—';
                $fechaFin = $r->fecha_final
                    ? \Carbon\Carbon::parse($r->fecha_final)->format('d/m/Y')   : '—';
                $fechaSol = $r->created_at
                    ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') : '—';
                $obs      = $r->observaciones ?? '';
                $tipo     = (int) ($r->tipo_nomina ?? 0);
                $periodo  = ($tipo > 0 && $r->fecha_inicial)
                    ? $calcularPeriodo($tipo, $r->fecha_inicial)
                    : 0;

                // Hoja 1 — Reporte RH
                $sheet1->setCellValue("A{$row}", (string) $r->nomina);
                $sheet1->setCellValue("B{$row}", $r->nombre);
                $sheet1->setCellValue("C{$row}", $r->centro_pago ?? '—');
                $sheet1->setCellValue("D{$row}", $fechaIni);
                $sheet1->setCellValue("E{$row}", $fechaFin);
                $sheet1->setCellValueExplicit(
                    "F{$row}", (int) $r->dias_habiles,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
                $sheet1->setCellValue("G{$row}", $r->tipo_permiso ?? '—');
                $sheet1->setCellValue("H{$row}", $r->estado_nombre ?? '—');
                $sheet1->setCellValue("I{$row}", $obs);
                $sheet1->setCellValue("J{$row}", $fechaSol);

                // Hoja 2 — TREESS-ASCII
                $obsCompleta = trim(
                    ($r->tipo_permiso ?? '') .
                    ($obs ? ' — ' . $obs : '')
                );

                $ascii = implode(',', [
                    $r->nomina,
                    $fechaIni,
                    $fechaFin,
                    (int) $r->dias_habiles,
                    0,
                    '',
                    $anioActual,
                    $tipo ?: '',
                    $periodo ?: '',
                    '"' . str_replace('"', '""', $obsCompleta) . '"',
                ]);

                $sheet2->setCellValue("A{$row}", (string) $r->nomina);
                $sheet2->setCellValue("B{$row}", $fechaIni);
                $sheet2->setCellValue("C{$row}", $fechaFin);
                $sheet2->setCellValueExplicit(
                    "D{$row}", (int) $r->dias_habiles,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
                $sheet2->setCellValueExplicit("E{$row}", 0,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
                $sheet2->setCellValue("F{$row}", '');
                $sheet2->setCellValueExplicit("G{$row}", $anioActual,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
                $sheet2->setCellValue("H{$row}", $tipo ?: '');
                $sheet2->setCellValue("I{$row}", $periodo ?: '');
                $sheet2->setCellValue("J{$row}", $obsCompleta);
                $sheet2->setCellValue("K{$row}", $ascii);

                $row++;
                $totalFilas++;
            }
        });

        // ── Anchos de columna ──────────────────────────────────────────────────
        foreach (['A'=>14,'B'=>30,'C'=>18,'D'=>13,'E'=>13,'F'=>13,'G'=>25,'H'=>22,'I'=>35,'J'=>20]
            as $col => $w) {
            $sheet1->getColumnDimension($col)->setWidth($w);
        }
        foreach (['A'=>13,'B'=>13,'C'=>13,'D'=>13,'E'=>14,'F'=>4,'G'=>12,'H'=>12,'I'=>14,'J'=>35,'K'=>60]
            as $col => $w) {
            $sheet2->getColumnDimension($col)->setWidth($w);
        }

        $sheet1->setAutoFilter('A1:J1');
        $sheet2->setAutoFilter('A1:K1');
        $spreadsheet->setActiveSheetIndex(0);

        // ── Auditoría ──────────────────────────────────────────────────────────
        $fuentePeriodo = count($quincenasPorFecha) > 0 ? 'BD' : 'manual';
        Auditoria::registrar(
            Auth::guard('empleado')->user()->nomina,
            'EXPORTAR_RESERVAS',
            "Excel 2 hojas | Estado: " . ($estado ?? 'todos') .
            " | Filas: {$totalFilas} | Quincena Q{$quincenaManual}/{$anioManual} [{$fuentePeriodo}]",
            request()->ip()
        );

        // ── Stream ─────────────────────────────────────────────────────────────
        $fn     = 'vacaciones_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        ob_end_clean();
        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fn}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }


    private function iniciales(string $nombre): string
    {
        $partes = explode(' ', trim($nombre));
        $ini    = strtoupper(substr($partes[0] ?? '?', 0, 1));
        $ini   .= strtoupper(substr($partes[1] ?? '', 0, 1));
        return $ini;
    }
}