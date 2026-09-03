<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Auditoria;

class ArchivarDatosAnuales extends Command
{
    protected $signature   = 'sistema:archivar
                                {--anio= : Año a archivar (default: año anterior)}
                                {--dry-run : Solo mostrar qué se archivaría, sin mover datos}
                                {--solo-auditorias : Archivar solo auditorías}
                                {--solo-reservas : Archivar solo reservas y su historial}';

    protected $description = 'Archiva auditorías, reservas e historial del año anterior a tablas históricas';

    public function handle(): int
    {
        $anio           = (int) ($this->option('anio') ?: now()->year - 1);
        $dryRun         = $this->option('dry-run');
        $soloAuditorias = $this->option('solo-auditorias');
        $soloReservas   = $this->option('solo-reservas');

        $this->info("=== Archivado de datos del año {$anio} ===");
        $this->info($dryRun ? '  Modo DRY-RUN — no se moverá nada' : '  Modo real');
        $this->newLine();

        $totalArchivados = 0;

        // Orden: auditorías → historial → reservas (historial depende de reservas por FK)
        if (!$soloReservas) {
            $totalArchivados += $this->archivarAuditorias($anio, $dryRun);
        }

        if (!$soloAuditorias) {
            $totalArchivados += $this->archivarHistorial($anio, $dryRun);
            $totalArchivados += $this->archivarReservas($anio, $dryRun);
        }

        $this->newLine();
        $this->info('Total ' . ($dryRun ? 'a archivar' : 'archivados') . ": {$totalArchivados} registros");

        if (!$dryRun) {
            Log::info('sistema:archivar ejecutado', [
                'anio'             => $anio,
                'total_archivados' => $totalArchivados,
            ]);

            try {
                Auditoria::registrar(
                    null,
                    'ARCHIVO_ANUAL',
                    "Año {$anio}: {$totalArchivados} registros archivados",
                    '127.0.0.1'
                );
            } catch (\Exception $e) {
                // No fallar si la auditoría no funciona
            }
        }

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDITORÍAS
    // ─────────────────────────────────────────────────────────────────────────
    private function archivarAuditorias(int $anio, bool $dryRun): int
    {
        $tabla = "auditorias_historico_{$anio}";

        $count = DB::table('auditorias')
            ->whereYear('fecha', $anio)
            ->count();

        $this->line("• Auditorías {$anio}: {$count} registros → [{$tabla}]");

        if ($count === 0 || $dryRun) {
            return $count;
        }

        // Crear tabla histórica con columnas explícitas y SIN IDENTITY
        // Si ya existe y fue creada mal (con IDENTITY), se detectará en el INSERT
        // DB::unprepared("
        //     IF OBJECT_ID('dbo.[{$tabla}]', 'U') IS NULL
        //     CREATE TABLE [{$tabla}] (
        //         [id_auditoria] [int]          NOT NULL,
        //         [empleado]     [varchar](255) NULL,
        //         [accion]       [varchar](100) NOT NULL,
        //         [detalles]     [varchar](500) NULL,
        //         [fecha]        [datetime2](7) NOT NULL,
        //         [ip_origen]    [varchar](45)  NULL,
        //         PRIMARY KEY CLUSTERED ([id_auditoria] ASC)
        //     );
        // ");

        // Copiar en lotes de 1000 con columnas explícitas (sin IDENTITY_INSERT)
        do {
            DB::statement("
                INSERT INTO [{$tabla}]
                    ([id_auditoria],[empleado],[accion],[detalles],[fecha],[ip_origen])
                SELECT TOP(1000)
                    [id_auditoria],[empleado],[accion],[detalles],[fecha],[ip_origen]
                FROM [auditorias]
                WHERE YEAR([fecha]) = {$anio}
                  AND [id_auditoria] NOT IN (SELECT [id_auditoria] FROM [{$tabla}])
            ");

            $enHistorico = DB::table($tabla)->whereYear('fecha', $anio)->count();
        } while ($enHistorico < $count);

        // Verificar integridad antes de borrar
        $enHistorico = DB::table($tabla)->whereYear('fecha', $anio)->count();

        if ($enHistorico < $count) {
            $this->error("  Conteo no coincide ({$enHistorico}/{$count}) — NO se eliminaron del origen");
            return 0;
        }

        $borrados = 0;
        do {
            $lote = DB::delete(
                'DELETE TOP(1000) FROM [auditorias] WHERE YEAR([fecha]) = ?',
                [$anio]
            );
            $borrados += $lote;
        } while ($lote > 0);

        $this->info("  {$borrados} auditorías archivadas en [{$tabla}]");
        return $borrados;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HISTORIAL DE RESERVAS
    // Nota: se archiva ANTES que las reservas para no romper la FK
    // ─────────────────────────────────────────────────────────────────────────
    private function archivarHistorial(int $anio, bool $dryRun): int
    {
        $tabla = "history_historico_{$anio}";

        $count = DB::table('history as h')
            ->join('reservas as r', 'h.id_reserva', '=', 'r.id_reserva')
            ->whereYear('r.fecha_inicial', $anio)
            ->whereYear('r.fecha_final', $anio)
            ->count();

        $this->line("• Historial {$anio}: {$count} registros → [{$tabla}]");

        if ($count === 0 || $dryRun) {
            return $count;
        }

        // // Crear tabla histórica con columnas explícitas y SIN IDENTITY
        // DB::unprepared("
        //     IF OBJECT_ID('dbo.[{$tabla}]', 'U') IS NULL
        //     CREATE TABLE [{$tabla}] (
        //         [id_history]      [int]          NOT NULL,
        //         [id_reserva]      [int]          NOT NULL,
        //         [estado_anterior] [int]          NULL,
        //         [estado_nuevo]    [int]          NOT NULL,
        //         [modificado_por]  [varchar](255) NOT NULL,
        //         [detalles_cambio] [varchar](500) NULL,
        //         [fecha_cambio]    [datetime2](7) NOT NULL,
        //         PRIMARY KEY CLUSTERED ([id_history] ASC)
        //     );
        // ");

        // Copiar en lotes con columnas explícitas
        do {
            DB::statement("
                INSERT INTO [{$tabla}]
                    ([id_history],[id_reserva],[estado_anterior],[estado_nuevo],
                     [modificado_por],[detalles_cambio],[fecha_cambio])
                SELECT TOP(1000)
                    h.[id_history],h.[id_reserva],h.[estado_anterior],h.[estado_nuevo],
                    h.[modificado_por],h.[detalles_cambio],h.[fecha_cambio]
                FROM [history] h
                INNER JOIN [reservas] r ON h.[id_reserva] = r.[id_reserva]
                WHERE YEAR(r.[fecha_inicial]) = {$anio}
                  AND YEAR(r.[fecha_final])   = {$anio}
                  AND h.[id_history] NOT IN (SELECT [id_history] FROM [{$tabla}])
            ");

            $enHistorico = DB::table($tabla)->count();
        } while ($enHistorico < $count);

        $enHistorico = DB::table($tabla)->count();
        $this->info("  {$enHistorico} registros de historial archivados en [{$tabla}]");

        // NO borrar aquí — se elimina automáticamente cuando se borren las reservas (ON DELETE CASCADE)
        return $enHistorico;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESERVAS CERRADAS
    // El historial se elimina en cascada al borrar las reservas
    // ─────────────────────────────────────────────────────────────────────────
    private function archivarReservas(int $anio, bool $dryRun): int
    {
        $tabla = "reservas_historico_{$anio}";

        // Solo reservas CERRADAS: 3=Rechazada Sup, 4=Aprobada, 5=Rechazada RH, 6=Cancelada
        $count = DB::table('reservas')
            ->whereYear('fecha_inicial', $anio)
            ->whereYear('fecha_final', $anio)
            ->whereIn('estado', [3, 4, 5, 6])
            ->count();

        $this->line("• Reservas cerradas {$anio}: {$count} registros → [{$tabla}]");

        if ($count === 0 || $dryRun) {
            return $count;
        }

        // Crear tabla histórica con columnas explícitas y SIN IDENTITY
        // DB::unprepared("
        //     IF OBJECT_ID('dbo.[{$tabla}]', 'U') IS NULL
        //     CREATE TABLE [{$tabla}] (
        //         [id_reserva]    [int]          NOT NULL,
        //         [fecha_inicial] [date]         NOT NULL,
        //         [fecha_final]   [date]         NOT NULL,
        //         [dias_habiles]  [int]          NULL,
        //         [id_empleado]   [varchar](255) NOT NULL,
        //         [id_tipo]       [int]          NOT NULL,
        //         [estado]        [int]          NOT NULL,
        //         [observaciones] [varchar](500) NULL,
        //         [deleted_at]    [datetime2](7) NULL,
        //         [created_at]    [datetime2](7) NOT NULL,
        //         [updated_at]    [datetime2](7) NOT NULL,
        //         PRIMARY KEY CLUSTERED ([id_reserva] ASC)
        //     );
        // ");

        // Copiar en lotes con columnas explícitas
        do {
            DB::statement("
                INSERT INTO [{$tabla}]
                    ([id_reserva],[fecha_inicial],[fecha_final],[dias_habiles],[id_empleado],
                     [id_tipo],[estado],[observaciones],[deleted_at],[created_at],[updated_at])
                SELECT TOP(500)
                    [id_reserva],[fecha_inicial],[fecha_final],[dias_habiles],[id_empleado],
                    [id_tipo],[estado],[observaciones],[deleted_at],[created_at],[updated_at]
                FROM [reservas]
                WHERE YEAR([fecha_inicial]) = {$anio}
                  AND YEAR([fecha_final])   = {$anio}
                  AND [estado]              IN (3,4,5,6)
                  AND [id_reserva]          NOT IN (SELECT [id_reserva] FROM [{$tabla}])
            ");

            $enHistorico = DB::table($tabla)->count();
        } while ($enHistorico < $count);

        $enHistorico = DB::table($tabla)->count();

        if ($enHistorico < $count) {
            $this->error("  ✗ Conteo no coincide ({$enHistorico}/{$count}) — NO se eliminaron reservas");
            return 0;
        }

        // Borrar history primero (aunque hay CASCADE, mejor explícito para lotes)
        $borradosHistory = 0;
        do {
            $lote = DB::delete("
                DELETE TOP(500) FROM [history]
                WHERE [id_reserva] IN (
                    SELECT [id_reserva] FROM [reservas]
                    WHERE YEAR([fecha_inicial]) = ?
                      AND YEAR([fecha_final])   = ?
                      AND [estado]              IN (3,4,5,6)
                )
            ", [$anio, $anio]);
            $borradosHistory += $lote;
        } while ($lote > 0);

        // Luego borrar reservas
        $borrados = 0;
        do {
            $lote = DB::delete("
                DELETE TOP(500) FROM [reservas]
                WHERE YEAR([fecha_inicial]) = ?
                  AND YEAR([fecha_final])   = ?
                  AND [estado]              IN (3,4,5,6)
            ", [$anio, $anio]);
            $borrados += $lote;
        } while ($lote > 0);

        $this->info("  {$borrados} reservas archivadas, {$borradosHistory} historiales eliminados");
        return $borrados;
    }
}