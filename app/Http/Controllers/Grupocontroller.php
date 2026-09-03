<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Grupo;
use App\Models\Empleado;
use App\Models\Auditoria;

class GrupoController extends Controller
{
    public function index()
    {
        $grupos = DB::table('grupos as g')
            ->join('empleados as e', 'g.supervisor', '=', 'e.nomina')
            ->leftJoin('grupo_empleado as ge', 'g.id_grupo', '=', 'ge.id_grupo')
            ->select(
                'g.id_grupo as id', 'g.nombre', 'g.supervisor',
                'e.nombre as supervisor_nombre',
                DB::raw('COUNT(ge.nomina) as total')
            )
            ->groupBy('g.id_grupo', 'g.nombre', 'g.supervisor', 'e.nombre')
            ->orderBy('g.nombre')
            ->get();

        return response()->json($grupos);
    }

    public function show(int $id)
    {
        $grupo      = Grupo::findOrFail($id);
        $supervisor = Empleado::where('nomina', $grupo->supervisor)->first();

        $miembros = DB::table('grupo_empleado as ge')
            ->join('empleados as e', 'ge.nomina', '=', 'e.nomina')
            ->join('roles as r',     'e.rol',     '=', 'r.id_rol')
            ->where('ge.id_grupo', $id)
            ->select('e.nomina', 'e.nombre', 'r.tipo as rol')
            ->orderBy('e.nombre')
            ->get()
            ->map(fn($m) => [
                'nomina'   => $m->nomina,
                'nombre'   => $m->nombre,
                'rol'      => $m->rol,
                'iniciales'=> implode('', array_map(
                    fn($w) => strtoupper(substr($w, 0, 1)),
                    array_slice(explode(' ', $m->nombre), 0, 2)
                )),
            ]);

        return response()->json([
            'id'                => $grupo->id_grupo,
            'nombre'            => $grupo->nombre,
            'supervisor'        => $grupo->supervisor,
            'supervisor_nombre' => $supervisor->nombre ?? '—',
            'miembros'          => $miembros,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'     => ['required', 'string', 'max:255'],
            'supervisor' => ['required', 'string', 'exists:empleados,nomina'],
        ]);

        $admin      = Auth::guard('empleado')->user();
        $supervisor = Empleado::where('nomina', $request->supervisor)
                              ->where('activo', 1)->firstOrFail();

        if ($supervisor->rol < 2) {
            $supervisor->update(['rol' => 2]);
        }

        $grupo = Grupo::create([
            'nombre'     => $request->nombre,
            'supervisor' => $request->supervisor,
        ]);

        Auditoria::registrar($admin->nomina, 'GRUPO_CREADO',
            "Grupo: {$grupo->nombre}", $request->ip());

        return response()->json(['message' => 'Grupo creado.', 'id' => $grupo->id_grupo]);
    }

    public function agregarMiembro(Request $request, int $id)
    {
        $request->validate([
            'nomina' => ['required', 'string', 'exists:empleados,nomina'],
        ]);

        $admin  = Auth::guard('empleado')->user();
        $nomina = $request->nomina;
        Grupo::findOrFail($id);

        $existe = DB::table('grupo_empleado')
            ->where('id_grupo', $id)->where('nomina', $nomina)->exists();

        if ($existe) {
            return response()->json(['error' => 'El empleado ya es miembro de este grupo.'], 422);
        }

        DB::table('grupo_empleado')->insert(['id_grupo' => $id, 'nomina' => $nomina]);

        Auditoria::registrar($admin->nomina, 'GRUPO_MIEMBRO_AGREGADO',
            "Grupo #{$id} | Nómina: {$nomina}", $request->ip());

        return response()->json(['message' => 'Empleado agregado correctamente.']);
    }

    // ── Importación masiva por JSON ───────────────────────────────
    // Formato esperado: [{"nomina":"123456"},{"nomina":"789012"}, ...] 
    // También acepta: ["123456","789012"]

    public function importarMasivo(Request $request, int $id)
    {
        $request->validate([
            'nominas' => ['required', 'array', 'min:1', 'max:500'],
        ]);

        $admin = Auth::guard('empleado')->user();
        Grupo::findOrFail($id);

        $nominasInput = $request->nominas;

        // Normalizar: acepta [{nomina:...}] o ["nomina"]
        $nominasLimpias = collect($nominasInput)->map(function ($item) {
            if (is_array($item) && isset($item['nomina'])) return trim((string) $item['nomina']);
            if (is_string($item) || is_numeric($item)) return trim((string) $item);
            return null;
        })->filter()->unique()->values()->toArray();

        if (empty($nominasLimpias)) {
            return response()->json(['error' => 'No se encontraron nóminas válidas en el JSON.'], 422);
        }

        // Verificar cuáles existen y están activos en BD
        $existentes = Empleado::where('activo', 1)
            ->whereIn('nomina', $nominasLimpias)
            ->pluck('nomina')
            ->toArray();

        $noExisten = array_diff($nominasLimpias, $existentes);

        // Ya miembros del grupo
        $yaMiembros = DB::table('grupo_empleado')
            ->where('id_grupo', $id)
            ->whereIn('nomina', $existentes)
            ->pluck('nomina')
            ->toArray();

        $nuevos = array_diff($existentes, $yaMiembros);

        $insertados = 0;
        foreach ($nuevos as $nomina) {
            DB::table('grupo_empleado')->insert(['id_grupo' => $id, 'nomina' => $nomina]);
            $insertados++;
        }

        Auditoria::registrar($admin->nomina, 'GRUPO_IMPORTACION_MASIVA',
            "Grupo #{$id} | Insertados: {$insertados} | No encontrados: " . count($noExisten),
            $request->ip());

        return response()->json([
            'message'      => "Importación completada.",
            'insertados'   => $insertados,
            'ya_miembros'  => count($yaMiembros),
            'no_encontrados' => array_values($noExisten),
        ]);
    }

    public function quitarMiembro(int $id, string $nomina)
    {
        $admin = Auth::guard('empleado')->user();
        $grupo = Grupo::findOrFail($id);

        if ($grupo->supervisor === $nomina) {
            return response()->json(['error' => 'No puedes quitar al supervisor del grupo.'], 422);
        }

        $eliminados = DB::table('grupo_empleado')
            ->where('id_grupo', $id)->where('nomina', $nomina)->delete();

        if (!$eliminados) {
            return response()->json(['error' => 'El empleado no pertenece a este grupo.'], 422);
        }

        Auditoria::registrar($admin->nomina, 'GRUPO_MIEMBRO_REMOVIDO',
            "Grupo #{$id} | Nómina: {$nomina}", request()->ip());

        return response()->json(['message' => 'Empleado removido del grupo.']);
    }

    public function cambiarSupervisor(Request $request, int $id)
    {
        $request->validate([
            'supervisor' => ['required', 'string', 'exists:empleados,nomina'],
        ]);

        $admin = Auth::guard('empleado')->user();
        $grupo = Grupo::findOrFail($id);
        $nuevo = Empleado::where('nomina', $request->supervisor)->where('activo', 1)->firstOrFail();

        DB::transaction(function () use ($grupo, $nuevo, $request) {
            if ($nuevo->rol < 2) $nuevo->update(['rol' => 2]);
            $grupo->update(['supervisor' => $request->supervisor]);
        });

        Auditoria::registrar($admin->nomina, 'GRUPO_SUPERVISOR_CAMBIADO',
            "Grupo #{$id} | Nuevo supervisor: {$request->supervisor}", $request->ip());

        return response()->json(['message' => 'Supervisor actualizado correctamente.']);
    }

    public function destroy(int $id)
    {
        $admin  = Auth::guard('empleado')->user();
        $grupo  = Grupo::findOrFail($id);
        $nombre = $grupo->nombre;
        $grupo->delete();

        // <- Invalidar cache de KPIs porque cambia la estructura de equipos
        \Illuminate\Support\Facades\Cache::forget('total_empleados_activos');
        // Limpiar todos los rangos de KPIs
        $this->invalidarCacheKpisAdmin();

        Auditoria::registrar($admin->nomina, 'GRUPO_ELIMINADO',
            "Grupo: {$nombre}", request()->ip());

        return response()->json(['message' => "Grupo \"{$nombre}\" eliminado."]);
    }

    // Agregar este helper en GrupoController
    private function invalidarCacheKpisAdmin(): void
    {
        $hoy = now();
        $rangos = ['todo', 'semana', 'mes', 'año', 'quincena'];
        foreach ($rangos as $rango) {
            // Forzar expiración inmediata — el AdminController reconstruirá el cache
            \Illuminate\Support\Facades\Cache::forget('admin_kpis_' . $rango . '_' . md5(json_encode(null)));
        }
    }

    // ── Buscar empleados ──────────────────────────────────────────
    // FIX: mínimo 1 carácter (antes era 2, bloqueaba nóminas de 1 dígito)
    public function buscarEmpleados(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $empleados = Empleado::where('activo', 1)
            ->where(fn($query) =>
                $query->where('nomina', 'like', "%{$q}%")
                      ->orWhere('nombre', 'like', "%{$q}%")
            )
            ->limit(10)
            ->get(['nomina', 'nombre']);

        return response()->json($empleados);
    }
}