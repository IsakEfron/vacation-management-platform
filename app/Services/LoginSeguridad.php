<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio de seguridad para el login.
 *
 * Reglas:
 *  - 5 intentos fallidos  -> bloqueo temporal de 2 minutos (en caché)
 *  - 10 intentos fallidos -> bloqueo permanente de usuario + IP (en BD)
 *
 * Claves de caché usadas:
 *  login_intentos:{nomina}   -> contador por nómina
 *  login_intentos:ip:{ip}    -> contador por IP
 *  login_bloq:{nomina}       -> flag de bloqueo temporal por nómina
 *  login_bloq:ip:{ip}        -> flag de bloqueo temporal por IP
 */
class LoginSeguridad
{
    // ── Límites configurables ────────────────────────────────────
    private const LIMITE_TEMPORAL    = 5;   // intentos antes del bloqueo temporal
    private const LIMITE_PERMANENTE  = 10;  // intentos antes del bloqueo permanente
    private const MINUTOS_BLOQUEO    = 2;   // duración del bloqueo temporal
    private const VENTANA_INTENTOS   = 30;  // minutos que se recuerdan los intentos

    // ────────────────────────────────────────────────────────────

    /**
     * Registra un intento fallido y aplica bloqueos si corresponde.
     * Devuelve un array con el estado actual.
     */
    public function registrarFallo(string $nomina, string $ip): array
    {
        // 1. Guardar en BD para historial y auditoría
        $this->guardarIntentoBD($nomina, $ip, exitoso: false);

        // 2. Incrementar contadores en caché
        $contadorNomina = $this->incrementarContador("login_intentos:{$nomina}");
        $contadorIp     = $this->incrementarContador("login_intentos:ip:{$ip}");

        // Usamos el máximo entre ambos contadores para decidir la acción
        $contador = max($contadorNomina, $contadorIp);

        // 3. Bloqueo PERMANENTE (10+ intentos)
        if ($contador >= self::LIMITE_PERMANENTE) {
            $this->aplicarBloqueoPermanente($nomina, $ip);
            return [
                'tipo'    => 'permanente',
                'mensaje' => 'Tu cuenta ha sido bloqueada por seguridad. Contacta al administrador.',
            ];
        }

        // 4. Bloqueo TEMPORAL (5+ intentos)
        if ($contador >= self::LIMITE_TEMPORAL) {
            $this->aplicarBloqueoTemporal($nomina, $ip);
            $restantes = self::LIMITE_PERMANENTE - $contador;
            return [
                'tipo'    => 'temporal',
                'minutos' => self::MINUTOS_BLOQUEO,
                'mensaje' => "Demasiados intentos fallidos. Espera " . self::MINUTOS_BLOQUEO . " minuto(s) antes de intentar de nuevo. "
                           . "Advertencia: {$restantes} intento(s) más bloquearán tu cuenta permanentemente.",
            ];
        }

        // 5. Solo advertir cuántos intentos quedan
        $restantesTemp = self::LIMITE_TEMPORAL - $contador;
        return [
            'tipo'    => 'advertencia',
            'mensaje' => "Credenciales incorrectas. Te quedan {$restantesTemp} intento(s) antes de un bloqueo temporal.",
        ];
    }

    /**
     * Registra un login exitoso y limpia los contadores.
     */
    public function registrarExito(string $nomina, string $ip): void
    {
        $this->guardarIntentoBD($nomina, $ip, exitoso: true);
        $this->limpiarContadores($nomina, $ip);
    }

    /**
     * Verifica si la nómina o IP están bloqueadas.
     * Devuelve null si no hay bloqueo, o un array con el tipo y mensaje.
     */
    public function verificarBloqueo(string $nomina, string $ip): ?array
    {
        // ── Bloqueo permanente en BD ──────────────────────────────

        // ¿La IP está bloqueada permanentemente?
        $ipBloqueada = DB::table('login_intentos')
            ->where('ip', $ip)
            ->whereNotNull('bloqueado_en')
            ->exists();

        if ($ipBloqueada) {
            return [
                'tipo'    => 'permanente',
                'mensaje' => 'Acceso bloqueado desde esta dirección IP. Contacta al administrador.',
            ];
        }

        // ¿El usuario tiene login_bloqueado = 1?
        $usuarioBloqueado = DB::table('empleados')
            ->where('nomina', $nomina)
            ->where('login_bloqueado', 1)
            ->exists();

        if ($usuarioBloqueado) {
            return [
                'tipo'    => 'permanente',
                'mensaje' => 'Tu cuenta está bloqueada. Contacta al administrador.',
            ];
        }

        // ── Bloqueo temporal en caché ─────────────────────────────

        if (Cache::has("login_bloq:{$nomina}") || Cache::has("login_bloq:ip:{$ip}")) {
            $ttl = Cache::get("login_bloq:{$nomina}") ?? Cache::get("login_bloq:ip:{$ip}");
            $segundosRestantes = max(0, $ttl - time());
            $minutosRestantes  = ceil($segundosRestantes / 60);

            return [
                'tipo'    => 'temporal',
                'mensaje' => "Cuenta temporalmente bloqueada. Intenta de nuevo en {$minutosRestantes} minuto(s).",
                'segundos' => $segundosRestantes,
            ];
        }

        return null; // Sin bloqueo
    }

    // ── Métodos privados ─────────────────────────────────────────

    private function incrementarContador(string $clave): int
    {
        $ttl = self::VENTANA_INTENTOS * 60; // segundos

        if (!Cache::has($clave)) {
            Cache::put($clave, 1, $ttl);
            return 1;
        }

        return Cache::increment($clave);
    }

    private function aplicarBloqueoTemporal(string $nomina, string $ip): void
    {
        $expira = time() + (self::MINUTOS_BLOQUEO * 60);

        Cache::put("login_bloq:{$nomina}",  $expira, self::MINUTOS_BLOQUEO * 60);
        Cache::put("login_bloq:ip:{$ip}",   $expira, self::MINUTOS_BLOQUEO * 60);
    }

    private function aplicarBloqueoPermanente(string $nomina, string $ip): void
    {
        // Bloquear el empleado en BD
        DB::table('empleados')
            ->where('nomina', $nomina)
            ->update(['login_bloqueado' => 1]);

        // Registrar la IP bloqueada
        DB::table('login_intentos')
            ->where('ip', $ip)
            ->whereNull('bloqueado_en')
            ->update(['bloqueado_en' => now()]);

        // Limpiar caché (ya no es necesario, está en BD)
        $this->limpiarContadores($nomina, $ip);
    }

    private function limpiarContadores(string $nomina, string $ip): void
    {
        Cache::forget("login_intentos:{$nomina}");
        Cache::forget("login_intentos:ip:{$ip}");
        Cache::forget("login_bloq:{$nomina}");
        Cache::forget("login_bloq:ip:{$ip}");
    }

    private function guardarIntentoBD(string $nomina, string $ip, bool $exitoso): void
    {
        DB::table('login_intentos')->insert([
            'nomina'   => $nomina,
            'ip'       => $ip,
            'fecha'    => now(),
            'exitoso'  => $exitoso ? 1 : 0,
        ]);
    }
}