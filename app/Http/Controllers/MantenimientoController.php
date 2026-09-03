<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Auditoria;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class MantenimientoController extends Controller
{
    // ── Listar mantenimientos ─────────────────────────────────────

    public function index()
    {
        $this->marcarVencidos(); // Auto-vencer programados expirados

        $rows = DB::table('mantenimientos as m')
            ->join('empleados as e', 'm.creado_por', '=', 'e.nomina')
            ->select(
                'm.id_mantenimiento', 'm.categoria', 'm.fecha_inicio',
                'm.fecha_fin', 'm.notas', 'm.estado',
                'e.nombre as creado_por_nombre'
            )
            ->orderByDesc('m.fecha_inicio')
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id_mantenimiento,
                'categoria'   => $m->categoria,
                'fecha_inicio'=> $m->fecha_inicio,
                'fecha_fin'   => $m->fecha_fin,
                'notas'       => $m->notas,
                'estado'      => (int) $m->estado,
                'creado_por'  => $m->creado_por_nombre,
            ]);

        return response()->json($rows);
    }

    // ── Marcar vencidos automáticamente ──────────────────────────
    // Convierte a estado 5 (Vencido) los mantenimientos Programados
    // cuya fecha_fin ya pasó y nunca se activaron.
    // Se ejecuta en index() y notificaciones() para que siempre esté actualizado.

    private function marcarVencidos(): void
    {
        // Ejecutar máximo una vez por minuto — el UPDATE es innecesario
        // si ya se ejecutó hace menos de 60 segundos
        $cacheKey = 'mant_vencidos_last_check';
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return; // Ya se verificó hace menos de 60s
        }

        DB::table('mantenimientos')
            ->where('estado', 1)
            ->where('fecha_fin', '<', now()->format('Y-m-d H:i:s'))
            ->update([
                'estado'     => 5,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60); // TTL 60 segundos
    }

    // ── Notificaciones (todos los roles autenticados) ─────────────

    public function notificaciones()
    {
        $this->marcarVencidos(); // Auto-vencer programados expirados

        // Mostrar:
        //  - Activos (estado 2): siempre
        //  - Programados (estado 1): cuya fecha_fin aún no haya pasado
        //    (incluye los que ya comenzaron pero no se activaron manualmente)
        $rows = DB::table('mantenimientos')
            ->where(function ($q) {
                $q->where('estado', 2)           // activos siempre
                  ->orWhere(function ($q2) {     // programados pendientes
                      $q2->where('estado', 1)
                         ->where('fecha_fin', '>=', now());  // que no hayan vencido
                  });
            })
            ->orderByRaw("CASE WHEN estado = 2 THEN 0 ELSE 1 END")  // activos primero
            ->orderBy('fecha_inicio')
            ->limit(5)
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id_mantenimiento,
                'categoria'   => $m->categoria,
                'fecha_inicio'=> $m->fecha_inicio,
                'fecha_fin'   => $m->fecha_fin,
                'notas'       => $m->notas,
                'estado'      => (int) $m->estado,
            ]);

        $activo = DB::table('mantenimientos')->where('estado', 2)->exists();

        return response()->json([
            'en_mantenimiento' => $activo,
            'proximos'         => $rows,
            'total'            => $rows->count(),
        ]);
    }

    // ── Crear mantenimiento programado ────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'categoria'    => ['required', 'string', 'max:100'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after:fecha_inicio'],
            'notas'        => ['nullable', 'string', 'max:500'],
        ], [
            'categoria.required'    => 'La categoría es obligatoria.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required'    => 'La fecha de fin es obligatoria.',
            'fecha_fin.after'       => 'La fecha de fin debe ser posterior a la de inicio.',
        ]);

        $sup = Auth::guard('empleado')->user();

        // HTML datetime-local envía "2026-04-04T13:55" — SQL Server necesita "2026-04-04 13:55:00"
        $fechaInicio = Carbon::parse($request->fecha_inicio)->format('Y-m-d H:i:s');
        $fechaFin    = Carbon::parse($request->fecha_fin)->format('Y-m-d H:i:s');

        $id = DB::table('mantenimientos')->insertGetId([
            'categoria'    => $request->categoria,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'notas'        => $request->notas,
            'estado'       => 1,
            'creado_por'   => $sup->nomina,
            'created_at'   => now()->format('Y-m-d H:i:s'),
            'updated_at'   => now()->format('Y-m-d H:i:s'),
        ]);

        Auditoria::registrar($sup->nomina, 'MANT_PROGRAMADO',
            "ID #{$id} | {$request->categoria}", $request->ip());

        return response()->json([
            'message' => 'Mantenimiento programado correctamente.',
            'id'      => $id,
        ]);
    }

    // ── Activar ───────────────────────────────────────────────────

    public function activar(int $id)
    {
        $sup  = Auth::guard('empleado')->user();
        $mant = DB::table('mantenimientos')->where('id_mantenimiento', $id)->first();

        if (!$mant) {
            return response()->json(['error' => 'Mantenimiento no encontrado.'], 404);
        }
        if ((int) $mant->estado !== 1) {
            return response()->json(['error' => 'Solo se pueden activar eventos en estado Programado.'], 422);
        }

        $otroActivo = DB::table('mantenimientos')->where('estado', 2)->exists();
        if ($otroActivo) {
            return response()->json(['error' => 'Ya hay un mantenimiento activo. Detén el actual primero.'], 422);
        }

        // Solo cambia el estado — NO sobreescribe fecha_inicio programada
        DB::table('mantenimientos')
            ->where('id_mantenimiento', $id)
            ->update([
                'estado'     => 2,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

        // Expulsar sesiones de roles 1-3 (si SESSION_DRIVER=database)
        try {
            DB::table('sessions')
                ->where('user_id', '!=', $sup->nomina)
                ->delete();
        } catch (\Exception $e) {
            // Ignorar si la tabla sessions no existe con ese esquema
        }

        Auditoria::registrar($sup->nomina, 'MANT_ACTIVADO',
            "ID #{$id}", request()->ip());

        \Illuminate\Support\Facades\Cache::forget('maintenance_mode_active');
        \Illuminate\Support\Facades\Cache::forget('sistema_estado');

        return response()->json(['message' => 'Sistema en modo mantenimiento. Usuarios desconectados.']);
    }

    // ── Detener ───────────────────────────────────────────────────

    public function detener(int $id)
    {
        $sup = Auth::guard('empleado')->user();

        $updated = DB::table('mantenimientos')
            ->where('id_mantenimiento', $id)
            ->where('estado', 2)
            ->update(['estado' => 3, 'fecha_fin' => now(), 'updated_at' => now()]);

        if (!$updated) {
            return response()->json(['error' => 'No hay un mantenimiento activo con ese ID.'], 422);
        }

        Auditoria::registrar($sup->nomina, 'MANT_DETENIDO',
            "ID #{$id}", request()->ip());

        \Illuminate\Support\Facades\Cache::forget('maintenance_mode_active');
        \Illuminate\Support\Facades\Cache::forget('sistema_estado');
        return response()->json(['message' => 'Mantenimiento completado. Sistema en línea.']);
    }

    // ── Cancelar ──────────────────────────────────────────────────

    public function cancelar(int $id)
    {
        $sup  = Auth::guard('empleado')->user();
        $mant = DB::table('mantenimientos')->where('id_mantenimiento', $id)->first();

        if (!$mant) {
            return response()->json(['error' => 'Mantenimiento no encontrado.'], 404);
        }
        if (!in_array((int) $mant->estado, [1, 2])) {
            return response()->json(['error' => 'Solo se pueden cancelar eventos Programados o Activos.'], 422);
        }

        DB::table('mantenimientos')
            ->where('id_mantenimiento', $id)
            ->update(['estado' => 4, 'updated_at' => now()]);

        Auditoria::registrar($sup->nomina, 'MANT_CANCELADO',
            "ID #{$id}", request()->ip());

        \Illuminate\Support\Facades\Cache::forget('maintenance_mode_active');
        \Illuminate\Support\Facades\Cache::forget('sistema_estado');
        return response()->json(['message' => 'Mantenimiento cancelado.']);
    }

    // ── Eliminar ──────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $sup  = Auth::guard('empleado')->user();
        $mant = DB::table('mantenimientos')->where('id_mantenimiento', $id)->first();

        if (!$mant) {
            return response()->json(['error' => 'Mantenimiento no encontrado.'], 404);
        }
        if (in_array((int) $mant->estado, [1, 2])) {
            return response()->json([
                'error' => 'Debes cancelar el mantenimiento antes de eliminar el registro.',
            ], 422);
        }

        DB::table('mantenimientos')->where('id_mantenimiento', $id)->delete();

        Auditoria::registrar($sup->nomina, 'MANT_ELIMINADO',
            "ID #{$id}", request()->ip());

        return response()->json(['message' => 'Registro eliminado.']);
    }

    // ── Estado del sistema ────────────────────────────────────────

    public function estado()
    {
        // Solo el SuperAdmin llama esto — el cache de 10s es suficiente
        $data = \Illuminate\Support\Facades\Cache::remember('sistema_estado', 30, function () {
            $activo = DB::table('mantenimientos')->where('estado', 2)->first();
            $ultimo = DB::table('mantenimientos')
                ->where('estado', 3)
                ->orderByDesc('fecha_fin')
                ->first();

            try {
                DB::connection()->getPdo();
                $dbOk = true;
            } catch (\Exception $e) {
                $dbOk = false;
            }

            return [
                'en_mantenimiento' => $activo !== null,
                'mantenimiento'    => $activo,
                'ultimo_mant'      => $ultimo
                    ? Carbon::parse($ultimo->fecha_fin)->format('d M Y H:i')
                    : 'Nunca',
                'db_ok'            => $dbOk,
            ];
        });

        return response()->json($data);
    }
        
    public function importarExcel(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $mantCheck = $this->checkMaintenance();
        if ($mantCheck) return $mantCheck;

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'modo'    => ['required', 'in:agregar,reemplazar'],
        ]);

        $sup  = Auth::guard('empleado')->user();
        $file = $request->file('archivo');

        // ── 1. Leer Excel ─────────────────────────────────────────────────────────
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getPathname());
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getPathname());
            // SIN setReadDataOnly — necesario para evaluar fórmulas con getCalculatedValue()
            $spreadsheet = $reader->load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $spreadsheet = $reader->load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo leer el archivo: ' . $e->getMessage()], 422);
        }

        // ── 2. Detectar columnas ──────────────────────────────────────────────────
        $mapKeys = [
            'nomina'      => ['num nomina','num_nomina','nomina','nómina','num nómina',
                            'numero nomina','número nómina','no. nomina','no nomina'],
            'nombre'      => ['nombre','nombre completo','nombre del empleado','trabajador'],
            'saldo'       => ['privac','pri vac','pri_vac','saldo','saldo vacacional',
                            'saldo vacaciones','dias vacaciones','días vacaciones','vacaciones',
                            'pimvac','prima vac'],
            'centro'      => ['centro de pago','centro_de_pago','centro pago','cp','centro'],
            'tipo_nomina' => ['tipo nomina','tipo_nomina','tipo de nomina','tipo nómina',
                            'tipo_nómina','periodicidad','tipo pago','quincenal semanal'],
        ];

        $colNomina = $colNombre = $colSaldo = $colCentro = $colTipoNomina = null;
        $headerRow  = 1;
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        for ($r = 1; $r <= min(10, $highestRow); $r++) {
            $hits    = 0;
            $rowData = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false, false)[0];
            $norm    = array_map(fn($c) => strtolower(trim((string) $c)), $rowData);
            foreach ($norm as $ci => $cell) {
                if (in_array($cell, $mapKeys['nomina'])) { $colNomina = $ci; $hits++; }
                if (in_array($cell, $mapKeys['nombre'])) { $colNombre = $ci; $hits++; }
                if (in_array($cell, $mapKeys['saldo']))  { $colSaldo  = $ci; $hits++; }
                if (in_array($cell, $mapKeys['centro'])) { $colCentro = $ci; $hits++; }
                if (in_array($cell, $mapKeys['tipo_nomina'])) { $colTipoNomina = $ci; $hits++; }
            }
            if ($hits >= 2) { $headerRow = $r; break; }
        }

        if ($colNomina === null || $colNombre === null) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            return response()->json(['error' => 'No se encontraron columnas NUM NOMINA y NOMBRE.'], 422);
        }

        // ── 3. Leer todas las filas en memoria (array simple, bajo overhead) ──────
        $empleados = [];   // array final limpio para el MERGE
        $omitidos  = 0;
        $seenNominas = []; // evitar duplicados dentro del mismo Excel

        $rowIterator = $sheet->getRowIterator($headerRow + 1);

        foreach ($rowIterator as $row) {
            $cellIter = $row->getCellIterator();
            $cellIter->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIter as $cell) {
                $rowData[] = $cell->getCalculatedValue();
            }

            $nomina = trim((string) ($rowData[$colNomina] ?? ''));
            $nombre = trim((string) ($rowData[$colNombre] ?? ''));
            $saldo  = $colSaldo !== null ? (int) floor((float) ($rowData[$colSaldo] ?? 0)) : 0;
            $centro = $colCentro !== null ? (trim((string) ($rowData[$colCentro] ?? '')) ?: null) : null;
            // Normalizar tipo_nomina: 1=semanal, 3=quincenal, 0=sin definir
            $tipoNominaRaw = $colTipoNomina !== null
                ? strtolower(trim((string) ($rowData[$colTipoNomina] ?? '')))
                : '';

            // Normalizar espacios múltiples
            $tipoNominaRaw = preg_replace('/\s+/', ' ', $tipoNominaRaw);

            // IMPORTANTE: preg_match PRIMERO, in_array después
            // match(true) evalúa en orden — el primero que sea true gana
            $tipoNomina = match(true) {
                // Regex primero — captura "1", "1-Sem", "1-semana", "1 sem", etc.
                (bool) preg_match('/^1[\s\-_]*(sem(ana)?|s)?$/i', $tipoNominaRaw) => 1,
                // Regex primero — captura "3", "3-Quin", "3-quincenal", "3 quin", etc.
                (bool) preg_match('/^3[\s\-_]*(quin(cenal)?|q)?$/i', $tipoNominaRaw) => 3,

                // Texto puro semanal
                in_array($tipoNominaRaw, [
                    'semanal', 'sem', 'semana', 's', 'weekly', 'week',
                ]) => 1,

                // Texto puro quincenal
                in_array($tipoNominaRaw, [
                    'quincenal', 'quin', 'quincena', 'q', 'biweekly', 'bisemanal', 'bi-semanal',
                ]) => 3,

                // Número puro como fallback
                is_numeric($tipoNominaRaw) && (int) $tipoNominaRaw === 1 => 1,
                is_numeric($tipoNominaRaw) && (int) $tipoNominaRaw === 3 => 3,

                default => 0,
            };

            if (empty($nomina) || empty($nombre)) { $omitidos++; continue; }

            // Deduplicar — si aparece dos veces en el Excel, conservar la última
            $seenNominas[$nomina] = [
                'nomina'      => $nomina,
                'nombre'      => $nombre,
                'saldo'       => $saldo,
                'centro'      => $centro,
                'tipo_nomina' => $tipoNomina,
            ];
        }

        $empleados = array_values($seenNominas);

        // Liberar memoria
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $seenNominas);
        gc_collect_cycles();

        if (empty($empleados)) {
            return response()->json(['error' => 'No se encontraron filas válidas en el archivo.'], 422);
        }

        // ── 4. Si modo reemplazar ─────────────────────────────────────────────────
        if ($request->modo === 'reemplazar') {
            DB::table('empleados')->whereIn('rol', [1, 2])->update(['activo' => 0]);
        }

        // ── 5. Construir SQL de valores para la tabla temporal ────────────────────
        $ahora     = now()->format('Y-m-d H:i:s');
        $valueRows = [];

        foreach ($empleados as $emp) {
            $nomina     = str_replace("'", "''", $emp['nomina']);
            $nombre     = str_replace("'", "''", $emp['nombre']);
            $saldo      = (int) $emp['saldo'];
            $tipoNomina = (int) ($emp['tipo_nomina'] ?? 0);
            $centro     = $emp['centro']
                ? "N'" . str_replace("'", "''", $emp['centro']) . "'"
                : 'NULL';

            $valueRows[] = "(N'{$nomina}', N'{$nombre}', {$saldo}, {$centro}, {$tipoNomina})";
        }

        // SQL Server soporta hasta 1000 filas por INSERT — dividir en bloques
        $chunks     = array_chunk($valueRows, 1000);
        $insertsSql = '';
        foreach ($chunks as $chunk) {
            // CORREGIDO: 5 columnas que coinciden con los 5 VALUES
            $insertsSql .= "INSERT INTO #import_tmp (nomina, nombre, saldo, centro_pago, tipo_nomina) VALUES\n"
                        . implode(",\n", $chunk) . ";\n";
        }

        // ── 6. Ejecutar TODO en un solo unprepared (misma conexión PDO) ───────────
        $fullSql = "
            IF OBJECT_ID('tempdb..#import_tmp') IS NOT NULL DROP TABLE #import_tmp;

            CREATE TABLE #import_tmp (
                nomina      VARCHAR(255) NOT NULL PRIMARY KEY,
                nombre      VARCHAR(120) NOT NULL,
                saldo       INT          NOT NULL DEFAULT 0,
                centro_pago VARCHAR(100) NULL,
                tipo_nomina TINYINT      NOT NULL DEFAULT 0
            );

            {$insertsSql}

            MERGE [empleados] AS target
            USING #import_tmp AS source
                ON target.[nomina] = source.[nomina]

            WHEN MATCHED THEN
                UPDATE SET
                    target.[nombre]      = source.[nombre],
                    target.[saldo]       = source.[saldo],
                    target.[centro_pago] = COALESCE(source.[centro_pago], target.[centro_pago]),
                    target.[tipo_nomina] = CASE
                                            WHEN source.[tipo_nomina] > 0 THEN source.[tipo_nomina]
                                            ELSE target.[tipo_nomina]
                                        END,
                    target.[activo]      = 1,
                    target.[updated_at]  = '{$ahora}'

            WHEN NOT MATCHED BY TARGET THEN
                INSERT (
                    [nomina], [nombre], [password], [saldo], [rol],
                    [activo], [login_bloqueado], [primera_vez],
                    [centro_pago], [tipo_nomina], [created_at], [updated_at]
                )
                VALUES (
                    source.[nomina],
                    source.[nombre],
                    CONCAT('\$md5\$', LOWER(CONVERT(VARCHAR(32), HASHBYTES('MD5', source.[nomina]), 2))),
                    source.[saldo],
                    1, 1, 0, 1,
                    source.[centro_pago],
                    source.[tipo_nomina],
                    '{$ahora}',
                    '{$ahora}'
                )

            WHEN NOT MATCHED BY SOURCE AND target.[rol] IN (1, 2) THEN
                UPDATE SET
                    target.[activo]     = 0,
                    target.[updated_at] = '{$ahora}';

            DROP TABLE #import_tmp;
        ";

        try {
            DB::unprepared($fullSql);
        } catch (\Exception $e) {
            return response()->json([
                'error'    => 'Error en el MERGE: ' . $e->getMessage(),
                'omitidos' => $omitidos,
            ], 500);
        }

        // ── 7. Contar resultados ──────────────────────────────────────────────────
        $insertados   = DB::table('empleados')
            ->where('created_at', $ahora)
            ->count();

        $actualizados = DB::table('empleados')
            ->where('updated_at', $ahora)
            ->where('created_at', '!=', $ahora)
            ->where('activo', 1)
            ->count();

        $desactivados = DB::table('empleados')
            ->where('updated_at', $ahora)
            ->where('created_at', '!=', $ahora)
            ->where('activo', 0)
            ->count();

        // Invalidar cache de empleados activos
        \Illuminate\Support\Facades\Cache::forget('total_empleados_activos');

        Auditoria::registrar(
            $sup->nomina,
            'IMPORTAR_EXCEL',
            "Modo:{$request->modo} Nuevos:{$insertados} Actualizados:{$actualizados} Desactivados:{$desactivados} Omitidos:{$omitidos}",
            $request->ip()
        );

        return response()->json([
            'message'      => 'Importación completada.',
            'insertados'   => $insertados,
            'actualizados' => $actualizados,
            'desactivados' => $desactivados,
            'omitidos'     => $omitidos,
            'errores'      => [],
        ]);
    }

    // ── Generar backup SQL ────────────────────────────────────────
    public function generarBackup()
    {
        set_time_limit(300);       
        ini_set('memory_limit', '256M');
        $sup = Auth::guard('empleado')->user();

        $tablas = [
            'roles', 'estado', 'tipo_solicitud', 'empleados',
            'grupos', 'grupo_empleado', 'reservas', 'history',
            'auditorias', 'mantenimientos',
        ];

        $fn = 'backup_vacaciones_' . now()->format('Ymd_His') . '.sql';

        Auditoria::registrar(
            $sup->nomina, 'BACKUP_GENERADO',
            'Respaldo SQL descargado', request()->ip()
        );

        // Stream directo — nunca acumula todo el SQL en RAM
        return response()->stream(function () use ($sup, $tablas) {
            echo "-- Respaldo Canel's Vacaciones — " . now()->format('Y-m-d H:i:s') . "\n";
            echo "-- Generado por: {$sup->nombre} ({$sup->nomina})\n\n";

            foreach ($tablas as $tabla) {
                try {
                    $total = DB::table($tabla)->count();
                    if ($total === 0) {
                        echo "-- Tabla {$tabla}: vacía\n\n";
                        continue;
                    }

                    echo "-- ── {$tabla} ({$total} filas) ─────────\n";
                    echo "DELETE FROM [{$tabla}];\nGO\n";

                    DB::table($tabla)
                        ->orderBy(DB::raw('(SELECT NULL)'))
                        ->chunk(500, function ($filas) use ($tabla) {
                            foreach ($filas as $fila) {
                                $fila = (array) $fila;
                                $cols = implode(', ', array_map(fn($c) => "[{$c}]", array_keys($fila)));
                                $vals = implode(', ', array_map(function ($v) {
                                    if ($v === null) return 'NULL';
                                    if (is_bool($v)) return $v ? '1' : '0';
                                    if (is_numeric($v) && !preg_match('/^0\d/', (string) $v)) return $v;
                                    return "N'" . str_replace("'", "''", (string) $v) . "'";
                                }, array_values($fila)));
                                echo "INSERT INTO [{$tabla}] ({$cols}) VALUES ({$vals});\n";
                            }
                            // Flush parcial para liberar buffer del servidor web
                            if (ob_get_level()) ob_flush();
                            flush();
                        });

                    echo "GO\n\n";
                } catch (\Exception $e) {
                    echo "-- Error en {$tabla}: {$e->getMessage()}\n\n";
                }
            }
        }, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fn}\"",
            'Cache-Control'       => 'no-cache',
            'X-Accel-Buffering'   => 'no', // Nginx: desactivar buffer para streaming
        ]);
    }

    // ── Restaurar backup ──────────────────────────────────────────

    public function restaurarBackup(Request $request)
    {
        $mantCheck = $this->checkMaintenance();
        if ($mantCheck) return $mantCheck;

        $request->validate(['archivo' => ['required', 'file', 'max:51200']]);

        $sup = Auth::guard('empleado')->user();
        $sql = file_get_contents($request->file('archivo')->getPathname());

        if (empty($sql)) {
            return response()->json(['error' => 'El archivo SQL está vacío.'], 422);
        }

        // Dividir por GO (SQL Server) o ;
        $stmts = preg_split('/;\s*\n|^GO\s*$/m', $sql);
        $stmts = array_filter(
            array_map('trim', $stmts),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );

        $ok = 0;
        $errores = [];

        DB::beginTransaction();
        try {
            foreach ($stmts as $stmt) {
                try {
                    DB::unprepared($stmt);
                    $ok++;
                } catch (\Exception $e) {
                    $errores[] = substr($stmt, 0, 80) . '… -> ' . $e->getMessage();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error crítico durante la restauración: ' . $e->getMessage(),
            ], 500);
        }

        Auditoria::registrar($sup->nomina, 'BACKUP_RESTAURADO',
            "Sentencias: {$ok}", $request->ip());

        return response()->json([
            'message'    => 'Restauración completada.',
            'ejecutados' => $ok,
            'errores'    => $errores,
        ]);
    }

    // ── Reiniciar sistema ─────────────────────────────────────────

    public function reiniciar(Request $request)
    {
        $mantCheck = $this->checkMaintenance();
        if ($mantCheck) return $mantCheck;

        $request->validate([
            'password_maestra' => ['required', 'string'],
        ]);

        $sup = Auth::guard('empleado')->user();

        if (!Hash::check($request->password_maestra, $sup->password)) {
            return response()->json(['error' => 'Contraseña incorrecta.'], 403);
        }

        DB::transaction(function () use ($sup) {
            DB::table('auditorias')->delete();
            DB::table('history')->delete();
            DB::table('reservas')->delete();
            DB::table('grupo_empleado')->delete();
            DB::table('grupos')->delete();
            try {
                DB::table('sessions')
                    ->where('user_id', '!=', $sup->nomina)
                    ->delete();
            } catch (\Exception $e) { /* sessions puede no existir */ }
            DB::table('login_intentos')->delete();
            DB::table('empleados')
                ->where('nomina', '!=', $sup->nomina)
                ->delete();
        });

        Auditoria::registrar($sup->nomina, 'SISTEMA_REINICIADO',
            'Reinicio completo del sistema', $request->ip());

        return response()->json([
            'message' => 'Sistema reiniciado. Todos los datos han sido eliminados.',
        ]);
    }

    // ── Helper: verificar modo mantenimiento ──────────────────────
    // Retorna una Response de error si NO hay mantenimiento activo,
    // o null si todo está bien. NO usa abort() para evitar HTML.

    private function checkMaintenance(): ?\Illuminate\Http\JsonResponse
    {
        $activo = DB::table('mantenimientos')->where('estado', 2)->exists();
        if (!$activo) {
            return response()->json([
                'error' => 'Esta operación requiere que el sistema esté en modo mantenimiento activo.',
            ], 423);
        }
        return null;
    }
}