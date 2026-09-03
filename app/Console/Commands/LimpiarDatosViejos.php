<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Auditoria;

class LimpiarDatosViejos extends Command
{
    protected $signature   = 'sistema:limpiar
                                {--dry-run : Solo mostrar cuántos registros se eliminarían, sin borrar}
                                {--dias-login-ok=90 : Días para conservar intentos exitosos}
                                {--dias-login-fail=30 : Días para conservar intentos fallidos sin bloqueo}';

    protected $description = 'Limpia registros obsoletos de login_intentos y otras tablas de soporte';

    public function handle(): int
    {
        $dryRun        = $this->option('dry-run');
        $diasLoginOk   = (int) $this->option('dias-login-ok');
        $diasLoginFail = (int) $this->option('dias-login-fail');

        $this->info('=== Limpieza de datos viejos ===');
        $this->info($dryRun ? '  Modo DRY-RUN — no se borrará nada' : '  Modo real — se borrarán registros');
        $this->newLine();

        $totalEliminados = 0;

        // ── 1. Intentos de login EXITOSOS de más de N días ────────────────────
        $countOk = DB::table('login_intentos')
            ->where('exitoso', 1)
            ->where('fecha', '<', now()->subDays($diasLoginOk))
            ->count();

        $this->line("• Intentos exitosos > {$diasLoginOk} días: {$countOk} registros");

        if (!$dryRun && $countOk > 0) {
            DB::table('login_intentos')
                ->where('exitoso', 1)
                ->where('fecha', '<', now()->subDays($diasLoginOk))
                ->delete();
            $this->info(" Eliminados: {$countOk}");
        }
        $totalEliminados += $countOk;

        // ── 2. Intentos FALLIDOS sin bloqueo de más de N días ─────────────────
        $countFail = DB::table('login_intentos')
            ->where('exitoso', 0)
            ->whereNull('bloqueado_en')
            ->where('fecha', '<', now()->subDays($diasLoginFail))
            ->count();

        $this->line("• Intentos fallidos sin bloqueo > {$diasLoginFail} días: {$countFail} registros");

        if (!$dryRun && $countFail > 0) {
            DB::table('login_intentos')
                ->where('exitoso', 0)
                ->whereNull('bloqueado_en')
                ->where('fecha', '<', now()->subDays($diasLoginFail))
                ->delete();
            $this->info("  Eliminados: {$countFail}");
        }
        $totalEliminados += $countFail;

        // ── 3. Sessions expiradas (por si el GC de Laravel no las limpió) ─────
        $sessionLifetime = config('session.lifetime', 120); // minutos
        $countSessions   = DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes($sessionLifetime * 2)->timestamp)
            ->count();

        $this->line("• Sessions expiradas: {$countSessions} registros");

        if (!$dryRun && $countSessions > 0) {
            // SQL Server compatible
            DB::delete("
                DELETE FROM [sessions] 
                WHERE [last_activity] < ?
            ", [now()->subMinutes($sessionLifetime * 2)->timestamp]);
            $this->info("  Eliminados: {$countSessions}");
        }
        $totalEliminados += $countSessions;

        // ── 4. Cache expirado en BD ───────────────────────────────────────────
        $countCache = DB::table('cache')
            ->where('expiration', '<', now()->timestamp)
            ->count();

        $this->line("• Entradas de cache expiradas: {$countCache} registros");

        if (!$dryRun && $countCache > 0) {
            DB::table('cache')
                ->where('expiration', '<', now()->timestamp)
                ->delete();
            $this->info("  Eliminados: {$countCache}");
        }
        $totalEliminados += $countCache;

        // ── Resumen ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->info("Total " . ($dryRun ? 'a eliminar' : 'eliminados') . ": {$totalEliminados} registros");

        if (!$dryRun) {
            Log::info('LimpiarDatosViejos ejecutado', [
                'login_ok'   => $countOk,
                'login_fail' => $countFail,
                'sessions'   => $countSessions,
                'cache'      => $countCache,
                'total'      => $totalEliminados,
            ]);

            // Registrar en auditoría del sistema
            try {
                Auditoria::registrar(
                    null,
                    'LIMPIEZA_AUTOMATICA',
                    "login_ok:{$countOk} login_fail:{$countFail} sessions:{$countSessions} cache:{$countCache}",
                    '127.0.0.1'
                );
            } catch (\Exception $e) {
                // No fallar si la auditoría no funciona
            }
        }

        return Command::SUCCESS;
    }
}