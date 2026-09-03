<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Auditoria;

class LimpiarAuditoriasAntiguas extends Command
{
    protected $signature   = 'auditorias:limpiar {--meses=12 : Meses a conservar}';
    protected $description = 'Limpia auditorías más antiguas de N meses en lotes de 1000 (compatible SQL Server)';

    public function handle(): int
    {
        $meses = (int) $this->option('meses');
        $corte = now()->subMonths($meses)->format('Y-m-d H:i:s');

        $total = DB::table('auditorias')
            ->where('fecha', '<', $corte)
            ->count();

        if ($total === 0) {
            $this->info("Sin auditorías anteriores a {$corte}.");
            return Command::SUCCESS;
        }

        $this->info("Encontrados {$total} registros anteriores a {$corte}. Eliminando en lotes...");

        $borrados = 0;
        do {
            // SQL Server requiere DELETE TOP(N) — se hace con unprepared o subquery
            $lote = DB::delete("
                DELETE TOP(1000) FROM [auditorias]
                WHERE [fecha] < ?
            ", [$corte]);

            $borrados += $lote;
            $this->line("  Lote: {$lote} eliminados (total: {$borrados}/{$total})");

        } while ($lote > 0);

        $this->info("Limpieza completada: {$borrados} registros eliminados.");

        Log::info('auditorias:limpiar ejecutado', [
            'corte'    => $corte,
            'borrados' => $borrados,
        ]);

        try {
            Auditoria::registrar(
                null,
                'LIMPIEZA_AUTOMATICA',
                "auditorias:{$borrados} registros eliminados (corte: {$corte})",
                '127.0.0.1'
            );
        } catch (\Exception $e) {
            // No fallar si la auditoría no funciona
        }

        return Command::SUCCESS;
    }
}