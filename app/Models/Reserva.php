<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Reserva extends Model
{
    use SoftDeletes;

    protected $table      = 'reservas';
    protected $primaryKey = 'id_reserva';

    protected $fillable = [
        'fecha_inicial', 'fecha_final', 'dias_habiles',
        'id_empleado', 'id_tipo', 'estado', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicial' => 'date',
        'fecha_final'   => 'date',
    ];

    // ── Relaciones ────────────────────────────────────────────────

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado', 'nomina');
    }

    public function estadoInfo()
    {
        return $this->belongsTo(Estado::class, 'estado', 'id_estado');
    }

    public function tipoInfo()
    {
        return $this->belongsTo(TipoSolicitud::class, 'id_tipo', 'id_tipo');
    }

    public function historial()
    {
        return $this->hasMany(History::class, 'id_reserva', 'id_reserva')
                    ->orderByDesc('fecha_cambio');
    }

    // ── Calcular días hábiles ─────────────────────────────────────
    // Considera:
    //   1. Días hábiles configurados por centro (centro_dias_habiles)
    //      Si el centro no tiene configuración -> regla global: L-S (días 1-6)
    //   2. Días especiales (feriados, puentes) de la tabla dias_especiales
    //      que aplican a 'todos' o al centro específico

    // En app/Models/Reserva.php — reemplazar el método calcularDiasHabiles
    public static function calcularDiasHabiles(Carbon $inicio, Carbon $fin, ?string $centroPago): int
    {
        // ── Cachear días hábiles del centro por 1 hora ────────────────────────────
        // La configuración de días hábiles por centro cambia muy raramente
        $cacheKeyCentro = 'dias_habiles_centro_' . md5($centroPago ?? 'global');

        $diasHabilesConfig = \Illuminate\Support\Facades\Cache::remember(
            $cacheKeyCentro, 3600,
            function () use ($centroPago) {
                $config = \Illuminate\Support\Facades\DB::table('centro_dias_habiles')
                    ->where('centro_pago', $centroPago ?? 'global')
                    ->where('es_habil', 1)
                    ->pluck('dia_semana')
                    ->toArray();

                // Si no hay config específica -> usar L-S (1-6 en isoWeekday)
                return empty($config) ? [1, 2, 3, 4, 5, 6] : $config;
            }
        );

        // ── Cachear días especiales del año actual + siguiente ────────────────────
        // Los días especiales cambian pocas veces al año
        $anio = $inicio->year;
        $cacheKeyDias = 'dias_especiales_' . $anio . '_' . md5($centroPago ?? 'todos');

        $diasEspeciales = \Illuminate\Support\Facades\Cache::remember(
            $cacheKeyDias, 1800, // 30 minutos — puede cambiar más que la config de centro
            function () use ($anio, $centroPago) {
                return \Illuminate\Support\Facades\DB::table('dias_especiales')
                    ->where('activo', 1)
                    ->whereYear('fecha', $anio)
                    ->where(fn($q) =>
                        $q->where('aplica_a', 'todos')
                        ->orWhere('aplica_a', $centroPago)
                    )
                    ->pluck('fecha')
                    ->map(fn($f) => \Carbon\Carbon::parse($f)->toDateString())
                    ->toArray();
            }
        );

        // ── Contar días hábiles ───────────────────────────────────────────────────
        $dias    = 0;
        $current = $inicio->copy()->startOfDay();
        $end     = $fin->copy()->startOfDay();

        while ($current->lte($end)) {
            $diaSemana  = $current->isoWeekday(); // 1=Lun...7=Dom
            $esFestivo  = in_array($current->toDateString(), $diasEspeciales);
            $esHabil    = in_array($diaSemana, $diasHabilesConfig);

            if ($esHabil && !$esFestivo) {
                $dias++;
            }

            $current->addDay();
        }

        return $dias;
    }

    // ── Calcular día de regreso ───────────────────────────────────
    // Primer día hábil después de la fecha final,
    // respetando la regla del centro y los feriados.

    public static function calcularRegreso(
        Carbon $fin,
        ?string $centroPago = null
    ): Carbon {
        $reglaDias = self::obtenerReglaDias($centroPago);
        $regreso   = $fin->copy()->addDay();

        // Obtener feriados en los próximos 14 días para cubrir puentes largos
        $feriados = self::obtenerFeriados(
            $regreso->toDateString(),
            $regreso->copy()->addDays(14)->toDateString(),
            $centroPago
        );

        $intentos = 0;
        while ($intentos < 30) {
            $diaSemana = $regreso->isoWeekday();
            $esFeriado = in_array($regreso->toDateString(), $feriados);

            if (in_array($diaSemana, $reglaDias) && !$esFeriado) {
                break;
            }

            $regreso->addDay();
            $intentos++;
        }

        return $regreso;
    }

    // ── Helpers privados ──────────────────────────────────────────

    /**
     * Devuelve array con los isoWeekday() que son hábiles para el centro.
     * Regla global: [1,2,3,4,5,6] = Lunes a Sábado.
     */
    private static function obtenerReglaDias(?string $centroPago): array
    {
        // Regla global por defecto: L-S
        $global = [1, 2, 3, 4, 5, 6];

        if (!$centroPago) {
            return $global;
        }

        try {
            $config = DB::table('centro_dias_habiles')
                ->where('centro_pago', $centroPago)
                ->get();

            if ($config->isEmpty()) {
                return $global; // Sin configuración -> usar global
            }

            // SQL Server BIT llega como "1"/"0" o 1/0 — cast explícito
            return $config
                ->filter(fn($d) => (int) $d->es_habil === 1)
                ->pluck('dia_semana')
                ->map(fn($d) => (int) $d)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            // Si la tabla no existe aún -> usar global
            return $global;
        }
    }

    /**
     * Devuelve array de fechas (Y-m-d) que son feriados en el rango,
     * aplicando los días especiales globales y los del centro.
     */
    private static function obtenerFeriados(
        string $fechaInicio,
        string $fechaFin,
        ?string $centroPago
    ): array {
        try {
            $query = DB::table('dias_especiales')
                ->where('activo', 1)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where(function ($q) use ($centroPago) {
                    $q->where('aplica_a', 'todos');
                    if ($centroPago) {
                        $q->orWhere('aplica_a', $centroPago);
                    }
                });

            return $query
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->toDateString())
                ->toArray();
        } catch (\Exception $e) {
            // Si la tabla no existe aún -> sin feriados
            return [];
        }
    }
}