<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\Auditoria; 

class AuditoriaController extends Controller
{
    // ── Listar auditorías (paginado + filtros) ────────────────────

    public function index(Request $request)
    {
        $query = DB::table('auditorias as a')
            ->leftJoin('empleados as e', 'a.empleado', '=', 'e.nomina')
            ->select(
                'a.id_auditoria', 'a.empleado', 'a.accion',
                'a.detalles', 'a.fecha', 'a.ip_origen',
                'e.nombre as nombre_empleado'
            )
            ->orderByDesc('a.fecha');

        if ($request->filled('accion')) {
            $query->where('a.accion', $request->accion);
        }
        if ($request->filled('buscar')) {
            $b = $request->buscar;
            $query->where(fn($q) =>
                $q->where('a.empleado', 'like', "%{$b}%")
                  ->orWhere('e.nombre',   'like', "%{$b}%")
                  ->orWhere('a.accion',   'like', "%{$b}%")
                  ->orWhere('a.detalles', 'like', "%{$b}%")
            );
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('a.fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('a.fecha', '<=', $request->fecha_hasta);
        }

        $paginated = $query->paginate(30);

        return response()->json([
            'data' => collect($paginated->items())->map(fn($a) => [
                'id'      => $a->id_auditoria,
                'nomina'  => $a->empleado ?? '—',
                'nombre'  => $a->nombre_empleado ?? 'Sistema',
                'accion'  => $a->accion,
                'detalles'=> $a->detalles,
                'fecha'   => $a->fecha ? Carbon::parse($a->fecha)->format('d M Y H:i') : '—',
                'ip'      => $a->ip_origen ?? '—',
            ]),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    // ── Catálogo de acciones disponibles ─────────────────────────

    public function acciones()
    {
        // Cache de 5 min — las acciones nuevas aparecen con cada evento de auditoría
        // No necesita ser inmediato, 5 min es suficiente para el filtro del UI
        $acciones = \Illuminate\Support\Facades\Cache::remember(
            'auditoria_acciones_catalogo', 300,
            fn() => DB::table('auditorias')
                ->distinct()
                ->orderBy('accion')
                ->pluck('accion')
        );

        return response()->json($acciones);
    }

    // ── Exportar auditorías a Excel ───────────────────────────────

    public function exportar(Request $request)
    {
        // Auditorías pueden tener cientos de miles de registros — prevenir timeout
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        // ── Construir query base con filtros ──────────────────────
        $query = DB::table('auditorias as a')
            ->leftJoin('empleados as e', 'a.empleado', '=', 'e.nomina')
            ->select(
                'a.id_auditoria', 'a.empleado', 'e.nombre as nombre_empleado',
                'a.accion', 'a.detalles', 'a.fecha', 'a.ip_origen'
            )
            ->orderByDesc('a.fecha');

        if ($request->filled('accion'))      $query->where('a.accion', $request->accion);
        if ($request->filled('buscar'))      $query->where('a.detalles', 'like', "%{$request->buscar}%");
        if ($request->filled('fecha_desde')) $query->whereDate('a.fecha', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('a.fecha', '<=', $request->fecha_hasta);

        // ── FIX 1: Contar con query de agregación, NO con ->get() ─
        // ->get() cargaría toda la tabla en RAM antes del chunk,
        // anulando completamente el beneficio del streaming.
        $totalRegistros = (clone $query)->count();

        // ── Crear Excel ───────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoría');

        // Metadata — fila 1-3
        $sheet->setCellValue('A1', "Reporte de Auditoría — Canel's");
        $sheet->setCellValue('A2', 'Generado: ' . now()->format('d/m/Y H:i'));
        $sheet->setCellValue('A3', 'Total registros: ' . $totalRegistros); // <- usa el count()
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setARGB('FF888888');

        // Encabezados — fila 5
        $headers = [
            'A5' => '#',          'B5' => 'Nómina',    'C5' => 'Nombre',
            'D5' => 'Acción',     'E5' => 'Detalles',  'F5' => 'Fecha / Hora',
            'G5' => 'IP Origen',
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Estilo del encabezado — UNA sola llamada, fuera del loop
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                             'color'       => ['argb' => 'FFDDDDDD']]],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(22);
        $sheet->freezePane('A6');
        $sheet->setAutoFilter('A5:G5');

        // ── FIX 2: Sin estilos por fila — chunk limpio ────────────
        // Colorear fila por fila dentro del chunk dispara el CPU de PhpSpreadsheet
        // en tablas grandes (10k+ registros). El tipo de acción ya está en la
        // columna D como texto — el usuario puede filtrar con Excel nativo.
        // Si necesita color, puede usar "Formato condicional" en Excel directamente.
        $row = 6;
        $query->chunk(500, function ($filas) use ($sheet, &$row) {
            foreach ($filas as $r) {
                $sheet->setCellValue("A{$row}", $r->id_auditoria);
                $sheet->setCellValue("B{$row}", $r->empleado ?? '—');
                $sheet->setCellValue("C{$row}", $r->nombre_empleado ?? 'Sistema');
                $sheet->setCellValue("D{$row}", $r->accion ?? '');
                $sheet->setCellValue("E{$row}", $r->detalles ?? '');
                $sheet->setCellValue("F{$row}", $r->fecha
                    ? Carbon::parse($r->fecha)->format('d/m/Y H:i:s')
                    : '—');
                $sheet->setCellValue("G{$row}", $r->ip_origen ?? '—');
                $row++;
            }
        });

        // Anchos de columna — fuera del loop, una sola pasada
        foreach (['A' => 8, 'B' => 12, 'C' => 28, 'D' => 28,
                  'E' => 50, 'F' => 20, 'G' => 16] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // ── Segunda hoja: resumen por acción ──────────────────────
        // Esta query es un COUNT GROUP BY — cero RAM, solo un resultado agregado
        $resumen = $spreadsheet->createSheet();
        $resumen->setTitle('Resumen');

        $resumen->setCellValue('A1', 'Acción');
        $resumen->setCellValue('B1', 'Total');
        $resumen->setCellValue('C1', '% del total');
        $resumen->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
        ]);

        $resumenData = DB::table('auditorias')
            ->selectRaw('accion, COUNT(*) as total')
            ->groupBy('accion')
            ->orderByDesc('total')
            ->get();

        // Total global para calcular porcentajes
        $totalGlobal = $resumenData->sum('total') ?: 1;

        $rRow = 2;
        foreach ($resumenData as $rd) {
            $pct = round(($rd->total / $totalGlobal) * 100, 1);
            $resumen->setCellValue("A{$rRow}", $rd->accion);
            $resumen->setCellValue("B{$rRow}", $rd->total);
            $resumen->setCellValue("C{$rRow}", "{$pct}%");
            $rRow++;
        }

        $resumen->getColumnDimension('A')->setWidth(32);
        $resumen->getColumnDimension('B')->setWidth(12);
        $resumen->getColumnDimension('C')->setWidth(14);
        $resumen->setAutoFilter('A1:C1');

        // Volver a hoja principal al abrir
        $spreadsheet->setActiveSheetIndex(0);

        // Auditoría del export
        Auditoria::registrar(
            Auth::guard('empleado')->user()->nomina,
            'AUDITORIA_EXPORTADA',
            "Filtros: accion={$request->accion} | buscar={$request->buscar} | Registros: {$totalRegistros}",
            request()->ip()
        );

        // ── Stream sin acumular en RAM ────────────────────────────
        $fn     = 'auditoria_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
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
}