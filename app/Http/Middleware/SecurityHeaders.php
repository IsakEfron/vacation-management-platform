<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const SQL_PATTERNS = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(--|\#|\/\*)/',
        '/(\bOR\b\s+[\'"]\d+[\'"]?\s*=\s*[\'"]\d+[\'"])/i',
        '/(\bAND\b\s+[\'"]\d+[\'"]?\s*=\s*[\'"]\d+[\'"])/i',
        '/\bEXEC\b\s*(\(|xp_)/i',
        '/\bCAST\b\s*\(/i',
        '/\bCONVERT\b\s*\(/i',
        '/\bDECLARE\b\s+@/i',
        '/\bWAITFOR\b\s+DELAY\b/i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Telescope maneja su propio CSP — no interferir
        if ($request->is('telescope', 'telescope/*')) {
            return $next($request);
        }
        
        if ($this->detectaSQLInjection($request)) {
            abort(400, 'Solicitud rechazada por seguridad.');
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options',  'nosniff');
        $response->headers->set('X-Frame-Options',         'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection',        '1; mode=block');
        $response->headers->set('Referrer-Policy',         'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy',      'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->buildCSP());

        return $response;
    }

    private function buildCSP(): string
    {
        $isDev = app()->environment('local', 'development');

        $viteSrc = $isDev ? 'http://localhost:5173 ws://localhost:5173' : '';

        // CDNs utilizados <- el proyecto
        $cdns = implode(' ', [
            'https://cdnjs.cloudflare.com',
            'https://unpkg.com',
            'https://cdn.jsdelivr.net',   // Phosphor Icons carga assets desde aquí
        ]);

        $scriptSrc  = trim("'self' 'unsafe-inline' {$cdns} {$viteSrc}");
        $styleSrc   = trim("'self' 'unsafe-inline' https://fonts.googleapis.com {$cdns} {$viteSrc}");
        $fontSrc    = "'self' https://fonts.gstatic.com {$cdns}";
        $connectSrc = trim("'self' {$viteSrc}");

        $directives = [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "font-src {$fontSrc}",
            "img-src 'self' data:",
            "connect-src {$connectSrc}",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    private function detectaSQLInjection(Request $request): bool
    {
        $inputs = array_merge(
            $request->query->all(),
            $request->request->all(),
        );

        foreach ($this->flattenArray($inputs) as $valor) {
            if (!is_string($valor)) continue;
            foreach (self::SQL_PATTERNS as $patron) {
                if (preg_match($patron, $valor)) {
                    try {
                        \App\Models\Auditoria::registrar(
                            optional(\Illuminate\Support\Facades\Auth::guard('empleado')->user())->nomina,
                            'SQLI_ATTEMPT',
                            "IP: {$request->ip()} | URL: {$request->url()}",
                            $request->ip()
                        );
                    } catch (\Exception) {}
                    return true;
                }
            }
        }
        return false;
    }

    private function flattenArray(array $arr): array
    {
        $result = [];
        array_walk_recursive($arr, function ($val) use (&$result) {
            $result[] = $val;
        });
        return $result;
    }
}