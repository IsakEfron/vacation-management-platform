<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Quincena;
use App\Models\Auditoria;
use Carbon\Carbon;

class QuincenaController extends Controller
{
    // ── Listar ────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = DB::table('quincenas as q')
            ->leftJoin('empleados as e', 'q.creado_por', '=', 'e.nomina')
            ->select('q.*', 'e.nombre as creado_por_nombre')
            ->orderByDesc('q.anio')
            ->orderBy('q.numero');

        if ($request->filled('anio')) {
            $query->where('q.anio', (int) $request->anio);
        }

        $activo = $request->input('activo', 1);
        $query->where('q.activo', $activo);

        return response()->json($query->get()->map(fn($q) => [
            'id'           => $q->id_quincena,
            'descripcion'  => $q->descripcion,
            'numero'       => $q->numero,
            'anio'         => $q->anio,
            'fecha_inicio' => $q->fecha_inicio,
            'fecha_fin'    => $q->fecha_fin,
            'activo'       => (bool) $q->activo,
            'creado_por'   => $q->creado_por_nombre ?? $q->creado_por ?? '—',
            'created_at'   => $q->created_at,
        ]));
    }

    // ── Crear ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'descripcion'  => ['required', 'string', 'max:100'],
            'numero'       => ['required', 'integer', 'between:1,24'],
            'anio'         => ['required', 'integer', 'between:2020,2099'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ], [
            'numero.between'            => 'El número de quincena debe estar entre 1 y 24.',
            'fecha_fin.after_or_equal'  => 'La fecha fin debe ser igual o posterior al inicio.',
        ]);

        $sup = Auth::guard('empleado')->user();

        // Verificar que no exista ya esa quincena en ese año
        $existe = DB::table('quincenas')
            ->where('numero', $request->numero)
            ->where('anio',   $request->anio)
            ->exists();

        if ($existe) {
            return response()->json([
                'error' => "La quincena {$request->numero} del año {$request->anio} ya existe.",
            ], 422);
        }

        // Verificar que las fechas no se traslapen con otra quincena del mismo año
        $traslape = DB::table('quincenas')
            ->where('anio', $request->anio)
            ->where('activo', 1)
            ->where(fn($q) =>
                $q->whereBetween('fecha_inicio', [$request->fecha_inicio, $request->fecha_fin])
                  ->orWhereBetween('fecha_fin',   [$request->fecha_inicio, $request->fecha_fin])
                  ->orWhere(fn($q2) =>
                      $q2->where('fecha_inicio', '<=', $request->fecha_inicio)
                         ->where('fecha_fin',    '>=', $request->fecha_fin)
                  )
            )
            ->exists();

        if ($traslape) {
            return response()->json([
                'error' => 'Las fechas se trasladan con otra quincena registrada del mismo año.',
            ], 422);
        }

        $id = DB::table('quincenas')->insertGetId([
            'descripcion'  => $request->descripcion,
            'numero'       => $request->numero,
            'anio'         => $request->anio,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'activo'       => 1,
            'creado_por'   => $sup->nomina,
            'created_at'   => now()->format('Y-m-d H:i:s'),
        ]);

        Cache::forget("quincenas_anio_{$request->anio}");

        Auditoria::registrar($sup->nomina, 'QUINCENA_CREADA',
            "Q{$request->numero}/{$request->anio} | {$request->fecha_inicio}–{$request->fecha_fin}",
            $request->ip());

        return response()->json(['message' => 'Quincena registrada correctamente.', 'id' => $id]);
    }

    // ── Editar ────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'descripcion'  => ['required', 'string', 'max:100'],
            'numero'       => ['required', 'integer', 'between:1,24'],
            'anio'         => ['required', 'integer', 'between:2020,2099'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $sup = Auth::guard('empleado')->user();
        $quincena = DB::table('quincenas')->where('id_quincena', $id)->first();

        if (!$quincena) {
            return response()->json(['error' => 'Quincena no encontrada.'], 404);
        }

        // Verificar que número+año no colisione con otra quincena distinta
        $colision = DB::table('quincenas')
            ->where('numero', $request->numero)
            ->where('anio',   $request->anio)
            ->where('id_quincena', '!=', $id)
            ->exists();

        if ($colision) {
            return response()->json([
                'error' => "Ya existe otra quincena {$request->numero} para {$request->anio}.",
            ], 422);
        }

        DB::table('quincenas')
            ->where('id_quincena', $id)
            ->update([
                'descripcion'  => $request->descripcion,
                'numero'       => $request->numero,
                'anio'         => $request->anio,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin'    => $request->fecha_fin,
                'updated_at'   => now()->format('Y-m-d H:i:s'),
            ]);

        Cache::forget("quincenas_anio_{$request->anio}");
        Cache::forget("quincenas_anio_{$quincena->anio}"); // si cambió el año

        Auditoria::registrar($sup->nomina, 'QUINCENA_EDITADA',
            "ID #{$id} | Q{$request->numero}/{$request->anio}", $request->ip());

        return response()->json(['message' => 'Quincena actualizada correctamente.']);
    }

    // ── Toggle activo/inactivo ────────────────────────────────────
    public function toggle(int $id)
    {
        $sup = Auth::guard('empleado')->user();
        $quincena = DB::table('quincenas')->where('id_quincena', $id)->first();

        if (!$quincena) {
            return response()->json(['error' => 'Quincena no encontrada.'], 404);
        }

        $nuevo = $quincena->activo ? 0 : 1;

        DB::table('quincenas')
            ->where('id_quincena', $id)
            ->update(['activo' => $nuevo, 'updated_at' => now()->format('Y-m-d H:i:s')]);

        Cache::forget("quincenas_anio_{$quincena->anio}");

        Auditoria::registrar($sup->nomina,
            $nuevo ? 'QUINCENA_ACTIVADA' : 'QUINCENA_DESACTIVADA',
            "ID #{$id} | Q{$quincena->numero}/{$quincena->anio}", request()->ip());

        return response()->json([
            'message' => $nuevo ? 'Quincena activada.' : 'Quincena desactivada.',
            'activo'  => (bool) $nuevo,
        ]);
    }

    // ── Hard delete ───────────────────────────────────────────────
    public function hardDestroy(int $id)
    {
        $sup = Auth::guard('empleado')->user();

        if ((int) $sup->rol !== 4) {
            return response()->json([
                'error' => 'Solo el SuperAdmin puede eliminar permanentemente.',
            ], 403);
        }

        $quincena = DB::table('quincenas')->where('id_quincena', $id)->first();

        if (!$quincena) {
            return response()->json(['error' => 'Quincena no encontrada.'], 404);
        }

        if ((int) $quincena->activo === 1) {
            return response()->json([
                'error' => 'Desactiva la quincena antes de eliminarla permanentemente.',
            ], 422);
        }

        DB::table('quincenas')->where('id_quincena', $id)->delete();

        Cache::forget("quincenas_anio_{$quincena->anio}");

        Auditoria::registrar($sup->nomina, 'QUINCENA_HARD_DELETE',
            "ID #{$id} | Q{$quincena->numero}/{$quincena->anio} | {$quincena->fecha_inicio}–{$quincena->fecha_fin}",
            request()->ip());

        return response()->json(['message' => 'Quincena eliminada permanentemente.']);
    }

    // ── Generar quincenas de un año automáticamente ───────────────
    // Genera las 24 quincenas estándar (1-15 y 16-fin de mes)
    // El admin puede editarlas después para ajustar las fechas reales
    public function generarAnio(Request $request)
    {
        $request->validate([
            'anio' => ['required', 'integer', 'between:2020,2099'],
        ]);

        $sup  = Auth::guard('empleado')->user();
        $anio = (int) $request->anio;

        // Verificar si ya existen quincenas para ese año
        $existentes = DB::table('quincenas')->where('anio', $anio)->count();
        if ($existentes > 0) {
            return response()->json([
                'error' => "Ya existen {$existentes} quincena(s) registradas para {$anio}. Edítalas individualmente.",
            ], 422);
        }

        $creadas = 0;
        $ahora   = now()->format('Y-m-d H:i:s');

        for ($mes = 1; $mes <= 12; $mes++) {
            $numQ1 = ($mes - 1) * 2 + 1;
            $numQ2 = ($mes - 1) * 2 + 2;

            $inicioQ1 = Carbon::create($anio, $mes, 1)->toDateString();
            $finQ1    = Carbon::create($anio, $mes, 15)->toDateString();
            $inicioQ2 = Carbon::create($anio, $mes, 16)->toDateString();
            $finQ2    = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

            $mesNombre = Carbon::create($anio, $mes, 1)->locale('es')->isoFormat('MMMM');

            DB::table('quincenas')->insert([
                [
                    'descripcion'  => "Q{$numQ1} — 1a. quincena " . ucfirst($mesNombre) . " {$anio}",
                    'numero'       => $numQ1,
                    'anio'         => $anio,
                    'fecha_inicio' => $inicioQ1,
                    'fecha_fin'    => $finQ1,
                    'activo'       => 1,
                    'creado_por'   => $sup->nomina,
                    'created_at'   => $ahora,
                ],
                [
                    'descripcion'  => "Q{$numQ2} — 2a. quincena " . ucfirst($mesNombre) . " {$anio}",
                    'numero'       => $numQ2,
                    'anio'         => $anio,
                    'fecha_inicio' => $inicioQ2,
                    'fecha_fin'    => $finQ2,
                    'activo'       => 1,
                    'creado_por'   => $sup->nomina,
                    'created_at'   => $ahora,
                ],
            ]);

            $creadas += 2;
        }

        Cache::forget("quincenas_anio_{$anio}");

        Auditoria::registrar($sup->nomina, 'QUINCENAS_GENERADAS',
            "Año {$anio} | {$creadas} quincenas creadas automáticamente", $request->ip());

        return response()->json([
            'message' => "{$creadas} quincenas generadas para {$anio}. Revisa y ajusta las fechas según el calendario real de Canel's.",
            'creadas' => $creadas,
        ]);
    }
}