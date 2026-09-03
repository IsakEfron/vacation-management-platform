<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope solo en entorno local — jamás en producción
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        if (!app()->runningInConsole()) {
            \Illuminate\Support\Facades\Cache::remember(
                'catalogo_roles', 3600,
                fn() => \App\Models\Rol::pluck('tipo', 'id_rol')->toArray()
            );
        }

        if ($this->app->environment('local')) {
            // ── Alertar queries lentas (> 300ms) ─────────────────────────────
            DB::listen(function ($query) {
                if ($query->time > 300) {
                    Log::warning(' Slow query', [
                        'sql'     => $query->sql,
                        'time_ms' => $query->time,
                        'file'    => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8)[7]['file'] ?? '?',
                    ]);
                }
            });

            // ── Contar queries por request y alertar si hay N+1 ──────────────
            $queryCount = 0;
            DB::listen(function () use (&$queryCount) {
                $queryCount++;
            });

            app()->terminating(function () use (&$queryCount) {
                if ($queryCount > 20) {
                    Log::warning(" Posible N+1: {$queryCount} queries en esta request", [
                        'url' => request()->fullUrl(),
                    ]);
                }
            });
        }

        // ── Límite de memoria en producción ───────────────────────────────────
        if ($this->app->environment('production')) {
            ini_set('memory_limit', '128M');
        }
    }
}