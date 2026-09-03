<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Auditoria;

class DiasEspecialesController extends Controller
{
    // ── Listar ────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $activo = $request->input('activo', 1);

        $query = DB::table('dias_especiales')
            ->where('activo', $activo)
            ->orderBy('fecha');

        if ($request->filled('anio')) {
            $query->whereYear('fecha', (int) $request->anio);
        }

        return response()->json($query->get()->map(fn($d) => [
            'id'            => $d->id_dia,
            'fecha'         => $d->fecha,
            'descripcion'   => $d->descripcion,
            'tipo'          => $d->tipo,
            'aplica_a'      => $d->aplica_a,
            'activo'        => (bool) $d->activo,
            'creado_por'    => $d->creado_por,
            'created_at'    => $d->created_at,
            'modificado_por'=> $d->modificado_por,
            'updated_at'    => $d->updated_at,
        ]));
    }

    // ── Toggle activo/inactivo ────────────────────────────────────
    public function toggleActivo(int $id)
    {
        $sup = Auth::guard('empleado')->user();

        $dia = DB::table('dias_especiales')->where('id_dia', $id)->first();
        if (!$dia) {
            return response()->json(['error' => 'Día no encontrado.'], 404);
        }

        $nuevoEstado = $dia->activo ? 0 : 1;

        DB::table('dias_especiales')
            ->where('id_dia', $id)
            ->update([
                'activo'         => $nuevoEstado,
                'modificado_por' => $sup->nomina,
                'updated_at'     => now()->format('Y-m-d H:i:s'),
            ]);

        $this->invalidarCacheDias();

        $accion = $nuevoEstado ? 'DIA_ESPECIAL_ACTIVADO' : 'DIA_ESPECIAL_DESACTIVADO';
        Auditoria::registrar($sup->nomina, $accion, "ID #{$id}", request()->ip());

        return response()->json([
            'message' => $nuevoEstado ? 'Día activado.' : 'Día desactivado.',
            'activo'  => (bool) $nuevoEstado,
        ]);
    }

    // ── Crear ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:200'],
            'tipo'        => ['required', 'in:feriado,puente,especial'],
            'aplica_a'    => ['required', 'string', 'max:100'],
        ]);

        $sup = Auth::guard('empleado')->user();

        $existe = DB::table('dias_especiales')
            ->where('fecha', $request->fecha)
            ->where('aplica_a', $request->aplica_a)
            ->where('activo', 1)
            ->exists();

        if ($existe) {
            return response()->json([
                'error' => 'Ya existe un día especial registrado para esa fecha en ese ámbito.',
            ], 422);
        }

        $id = DB::table('dias_especiales')->insertGetId([
            'fecha'       => $request->fecha,
            'descripcion' => $request->descripcion,
            'tipo'        => $request->tipo,
            'aplica_a'    => $request->aplica_a,
            'activo'      => 1,
            'creado_por'  => $sup->nomina,
            'created_at'  => now()->format('Y-m-d H:i:s'),
        ]);

        $this->invalidarCacheDias();

        Auditoria::registrar($sup->nomina, 'DIA_ESPECIAL_CREADO',
            "{$request->fecha} | {$request->descripcion}", $request->ip());

        return response()->json(['message' => 'Día especial registrado.', 'id' => $id]);
    }

    // ── Editar ────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:200'],
            'tipo'        => ['required', 'in:feriado,puente,especial'],
            'aplica_a'    => ['required', 'string', 'max:100'],
        ]);

        $sup = Auth::guard('empleado')->user();

        $colision = DB::table('dias_especiales')
            ->where('fecha', $request->fecha)
            ->where('aplica_a', $request->aplica_a)
            ->where('id_dia', '!=', $id)
            ->exists();

        if ($colision) {
            return response()->json([
                'error' => 'Ya existe otro día especial para esa fecha en ese ámbito.',
            ], 422);
        }

        DB::table('dias_especiales')
            ->where('id_dia', $id)
            ->update([
                'fecha'          => $request->fecha,
                'descripcion'    => $request->descripcion,
                'tipo'           => $request->tipo,
                'aplica_a'       => $request->aplica_a,
                'modificado_por' => $sup->nomina,
                'updated_at'     => now()->format('Y-m-d H:i:s'),
            ]);

        $this->invalidarCacheDias();

        Auditoria::registrar($sup->nomina, 'DIA_ESPECIAL_EDITADO',
            "ID #{$id} | {$request->fecha} | {$request->descripcion}", request()->ip());

        return response()->json(['message' => 'Día especial actualizado.']);
    }

    // ── Eliminar permanentemente ──────────────────────────────────
    public function hardDestroyDia(int $id)
    {
        $sup = Auth::guard('empleado')->user();

        if ((int) $sup->rol !== 4) {
            return response()->json(['error' => 'Solo el SuperAdmin puede eliminar permanentemente.'], 403);
        }

        $dia = DB::table('dias_especiales')->where('id_dia', $id)->first();

        if (!$dia) {
            return response()->json(['error' => 'Día no encontrado.'], 404);
        }

        if ((int) $dia->activo === 1) {
            return response()->json([
                'error' => 'Desactiva el día especial antes de eliminarlo permanentemente.',
            ], 422);
        }

        DB::table('dias_especiales')->where('id_dia', $id)->delete();

        $this->invalidarCacheDias();

        Auditoria::registrar($sup->nomina, 'DIA_ESPECIAL_HARD_DELETE',
            "ID #{$id} | {$dia->fecha} | {$dia->descripcion} | {$dia->aplica_a}",
            request()->ip());

        return response()->json(['message' => 'Día especial eliminado permanentemente.']);
    }

    // ── Centros ───────────────────────────────────────────────────
    public function centros()
    {
        $centros = DB::table('centro_dias_habiles')
            ->orderBy('centro_pago')
            ->orderBy('dia_semana')
            ->get()
            ->groupBy('centro_pago')
            ->map(fn($dias) => $dias->keyBy('dia_semana'));

        $todosCentros = DB::table('empleados')
            ->whereNotNull('centro_pago')
            ->where('activo', 1)
            ->distinct()
            ->pluck('centro_pago')
            ->sort()
            ->values();

        return response()->json([
            'configurados' => $centros,
            'centros'      => $todosCentros,
        ]);
    }

    // ── Guardar configuración de días hábiles ─────────────────────
    public function guardarCentro(Request $request)
    {
        $request->validate([
            'centro_pago' => ['required', 'string', 'max:100'],
            'dias'        => ['required', 'array'],
            'dias.*'      => ['integer', 'between:1,7'],
        ]);

        $sup = Auth::guard('empleado')->user();

        DB::table('centro_dias_habiles')
            ->where('centro_pago', $request->centro_pago)
            ->delete();

        $diasHabiles = $request->dias;
        for ($d = 1; $d <= 7; $d++) {
            DB::table('centro_dias_habiles')->insert([
                'centro_pago' => $request->centro_pago,
                'dia_semana'  => $d,
                'es_habil'    => in_array($d, $diasHabiles) ? 1 : 0,
            ]);
        }

        // Invalidar también el cache de configuración de días hábiles del centro
        Cache::forget('dias_habiles_centro_' . md5($request->centro_pago));

        Auditoria::registrar($sup->nomina, 'CENTRO_DIAS_ACTUALIZADO',
            "Centro: {$request->centro_pago} | Días: " . implode(',', $diasHabiles),
            $request->ip());

        return response()->json(['message' => "Configuración de {$request->centro_pago} guardada."]);
    }

    // ── Eliminar configuración de centro ──────────────────────────
    public function eliminarCentro(string $centro)
    {
        $sup = Auth::guard('empleado')->user();

        DB::table('centro_dias_habiles')
            ->where('centro_pago', $centro)
            ->delete();

        Cache::forget('dias_habiles_centro_' . md5($centro));

        Auditoria::registrar($sup->nomina, 'CENTRO_DIAS_ELIMINADO',
            "Centro: {$centro}", request()->ip());

        return response()->json([
            'message' => "Configuración de {$centro} eliminada. Usará regla global (Lunes a Sabado).",
        ]);
    }

    // ── Helper: invalidar cache de días especiales ────────────────
    private function invalidarCacheDias(): void
    {
        $anioActual = now()->year;
        $anioSig    = $anioActual + 1;

        $centros = DB::table('empleados')
            ->whereNotNull('centro_pago')
            ->where('activo', 1)
            ->distinct()
            ->pluck('centro_pago')
            ->toArray();

        $centros[] = null;

        foreach ($centros as $centro) {
            $key = md5($centro ?? 'todos');
            Cache::forget("dias_especiales_{$anioActual}_{$key}");
            Cache::forget("dias_especiales_{$anioSig}_{$key}");
        }
    }
}