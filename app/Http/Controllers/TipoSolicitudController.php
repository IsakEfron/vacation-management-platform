<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\TipoSolicitud;
use App\Models\Auditoria;

class TipoSolicitudController extends Controller
{
    // ── Listar ────────────────────────────────────────────────────
    public function index()
    {
        $tipos = TipoSolicitud::orderBy('id_tipo')->get()->map(fn($t) => [
            'id'        => $t->id_tipo,
            'nombre'    => $t->nombre,
            'con_goce'  => $t->con_goce,
            'usa_saldo' => $t->usa_saldo,
            'activo'    => $t->activo ?? true, // si agregas columna activo
        ]);

        return response()->json($tipos);
    }

    // ── Crear ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'con_goce'  => ['required', 'boolean'],
            'usa_saldo' => ['required', 'boolean'],
        ]);

        $sup = Auth::guard('empleado')->user();

        $existe = TipoSolicitud::where('nombre', $request->nombre)->exists();
        if ($existe) {
            return response()->json([
                'error' => 'Ya existe un tipo de solicitud con ese nombre.',
            ], 422);
        }

        $tipo = TipoSolicitud::create([
            'nombre'    => $request->nombre,
            'con_goce'  => (bool) $request->con_goce,
            'usa_saldo' => (bool) $request->usa_saldo,
        ]);

        Cache::forget('catalogo_tipos_solicitud');

        Auditoria::registrar($sup->nomina, 'TIPO_SOLICITUD_CREADO',
            "ID #{$tipo->id_tipo} | {$tipo->nombre}", $request->ip());

        return response()->json(['message' => 'Tipo de solicitud creado.', 'id' => $tipo->id_tipo]);
    }

    // ── Editar ────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'con_goce'  => ['required', 'boolean'],
            'usa_saldo' => ['required', 'boolean'],
        ]);

        $sup  = Auth::guard('empleado')->user();
        $tipo = TipoSolicitud::findOrFail($id);

        // Verificar nombre único excluyendo el actual
        $colision = TipoSolicitud::where('nombre', $request->nombre)
            ->where('id_tipo', '!=', $id)
            ->exists();

        if ($colision) {
            return response()->json([
                'error' => 'Ya existe otro tipo con ese nombre.',
            ], 422);
        }

        $tipo->update([
            'nombre'    => $request->nombre,
            'con_goce'  => (bool) $request->con_goce,
            'usa_saldo' => (bool) $request->usa_saldo,
        ]);

        Cache::forget('catalogo_tipos_solicitud');

        Auditoria::registrar($sup->nomina, 'TIPO_SOLICITUD_EDITADO',
            "ID #{$id} | {$request->nombre}", $request->ip());

        return response()->json(['message' => 'Tipo actualizado correctamente.']);
    }

    // ── Toggle activo — requiere columna activo en tipo_solicitud ─
    // Si no quieres agregar la columna, usar soft-delete o simplemente
    // no permitir eliminar tipos que tengan reservas asociadas.
    public function toggle(int $id)
    {
        $sup  = Auth::guard('empleado')->user();
        $tipo = TipoSolicitud::findOrFail($id);

        // Proteger: no desactivar si tiene reservas activas
        $reservasActivas = DB::table('reservas')
            ->where('id_tipo', $id)
            ->whereNull('deleted_at')
            ->whereNotIn('estado', [3, 5, 6])
            ->count();

        if ($reservasActivas > 0 && $tipo->activo) {
            return response()->json([
                'error' => "No se puede desactivar: hay {$reservasActivas} solicitud(es) activa(s) con este tipo.",
            ], 422);
        }

        $tipo->update(['activo' => !$tipo->activo]);

        Cache::forget('catalogo_tipos_solicitud');

        Auditoria::registrar($sup->nomina,
            $tipo->activo ? 'TIPO_SOLICITUD_ACTIVADO' : 'TIPO_SOLICITUD_DESACTIVADO',
            "ID #{$id} | {$tipo->nombre}", request()->ip());

        return response()->json([
            'message' => $tipo->activo ? 'Tipo activado.' : 'Tipo desactivado.',
            'activo'  => $tipo->activo,
        ]);
    }
}