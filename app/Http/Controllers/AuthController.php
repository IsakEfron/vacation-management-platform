<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Empleado;
use App\Models\Auditoria;
use App\Services\LoginSeguridad;

class AuthController extends Controller
{
    public function __construct(private LoginSeguridad $seguridad) {}

    public function showLogin()
    {
        if (Auth::guard('empleado')->check()) {
            return $this->redirectByRole(Auth::guard('empleado')->user());
        }
        return view('index');
    }

    public function login(Request $request)
    {
        $request->validate([
            'usuario'  => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'usuario.required'  => 'El número de nómina es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $nomina = trim($request->usuario);
        $ip     = $request->ip();

        $bloqueo = $this->seguridad->verificarBloqueo($nomina, $ip);
        if ($bloqueo) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => $bloqueo['mensaje']])
                ->with('bloqueo_tipo', $bloqueo['tipo'])
                ->with('bloqueo_segundos', $bloqueo['segundos'] ?? null);
        }

        $empleado = Empleado::where('nomina', $nomina)
                            ->where('activo', 1)
                            ->first();

        if (!$empleado) {
            $resultado = $this->seguridad->registrarFallo($nomina, $ip);
            Auditoria::registrar(null, 'LOGIN_FALLIDO', "Nómina: {$nomina} | IP: {$ip}", $ip);
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => $resultado['mensaje']])
                ->with('bloqueo_tipo', $resultado['tipo']);
        }

        $passwordValida = str_starts_with($empleado->password, '$md5$')
            ? hash_equals($empleado->password, '$md5$' . md5($request->password))
            : Hash::check($request->password, $empleado->password);

        if (!$passwordValida) {
            $resultado = $this->seguridad->registrarFallo($nomina, $ip);
            Auditoria::registrar(null, 'LOGIN_FALLIDO', "Nómina: {$nomina} | IP: {$ip}", $ip);
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => $resultado['mensaje']])
                ->with('bloqueo_tipo', $resultado['tipo']);
        }

        if ($empleado->login_bloqueado) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Tu cuenta está bloqueada. Contacta al administrador.'])
                ->with('bloqueo_tipo', 'permanente');
        }

        if ($empleado->rol < 4 && $this->hayMantenimientoActivo()) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'El sistema está en mantenimiento. Intente más tarde.']);
        }

        // ── Login exitoso ─────────────────────────────────────────────
        $this->seguridad->registrarExito($nomina, $ip);

        // 1. Regenerar PRIMERO — previene session fixation
        $request->session()->regenerate();

        // 2. Autenticar con el guard
        Auth::guard('empleado')->login($empleado, $request->boolean('remember'));

        // 3. Guardar metadatos en la sesión YA regenerada
        $request->session()->put('last_activity', time());
        $request->session()->put('session_user', $empleado->nomina);
        $request->session()->put('primera_vez', (bool) $empleado->primera_vez);

        Auditoria::registrar($nomina, 'LOGIN', 'Inicio de sesión exitoso', $ip);

        return $this->redirectByRole($empleado);
    }

    public function logout(Request $request)
    {
        $nomina = Auth::guard('empleado')->id();
        Auditoria::registrar($nomina, 'LOGOUT', 'Cierre de sesión', $request->ip());
        Auth::guard('empleado')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $empleado = Auth::guard('empleado')->user();

        // Verificar contraseña actual — soporta bcrypt y MD5 temporal
        $passwordActualValida = str_starts_with($empleado->password, '$md5$')
            ? hash_equals($empleado->password, '$md5$' . md5($request->current_password))
            : Hash::check($request->current_password, $empleado->password);

        if (!$passwordActualValida) {
            return response()->json(['error' => 'La contraseña actual es incorrecta.'], 422);
        }

        $eraPrimeraVez = (bool) $empleado->primera_vez;

        // Al guardar, SIEMPRE se convierte a bcrypt (reemplaza el MD5 temporal)
        $empleado->update([
            'password'    => Hash::make($request->new_password),
            'primera_vez' => 0,
            'updated_at'  => now(),
        ]);

        $request->session()->forget('primera_vez');

        Auditoria::registrar(
            $empleado->nomina,
            'CAMBIO_PASSWORD',
            $eraPrimeraVez ? 'Cambio obligatorio primer login' : 'Contraseña actualizada',
            $request->ip()
        );

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    private function redirectByRole(Empleado $empleado)
    {
        return match ($empleado->rol) {
            4, 3    => redirect()->route('admin'),
            2       => redirect()->route('sup_user'),
            default => redirect()->route('users'),
        };
    }

    private function hayMantenimientoActivo(): bool
    {
        return \Illuminate\Support\Facades\DB::table('mantenimientos')
            ->where('estado', 2)   // 2 = Activo
            ->exists();
    }
}