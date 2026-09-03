<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanitiza los inputs de texto para prevenir XSS.
 *
 * Laravel ya escapa las variables en Blade con {{ }},
 * pero este middleware añade una capa extra limpiando
 * los datos ANTES de que lleguen al controlador.
 *
 * Campos excluidos de la sanitización (pueden contener HTML):
 * Se configuran en $camposExcluidos.
 */
class SanitizeInput
{
    // Campos que NO se sanitizan (por si alguno acepta HTML en el futuro)
    private array $camposExcluidos = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $inputs = $request->except($this->camposExcluidos);
        $sanitizados = $this->sanitizarArray($inputs);

        // Reemplazar los inputs en el request (sin tocar los excluidos)
        $request->merge($sanitizados);

        return $next($request);
    }

    private function sanitizarArray(array $datos): array
    {
        return array_map(function ($valor) {
            if (is_array($valor)) {
                return $this->sanitizarArray($valor);
            }
            if (is_string($valor)) {
                return $this->sanitizarString($valor);
            }
            return $valor;
        }, $datos);
    }

    private function sanitizarString(string $valor): string
    {
        // 1. Eliminar tags HTML peligrosos
        $valor = strip_tags($valor);

        // 2. Convertir caracteres especiales a entidades HTML
        $valor = htmlspecialchars($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 3. Eliminar caracteres de control invisibles (null bytes, etc.)
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $valor);

        // 4. Limpiar espacios al inicio/final
        return trim($valor);
    }
}