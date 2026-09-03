<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\DiasEspecialesController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\QuincenaController;
use App\Http\Controllers\TipoSolicitudController;

// ─── Públicas ─────────────────────────────────────────────────────────────────
Route::middleware('guest:empleado')->group(function () {
    Route::get('/',       [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// ─── Auth — sin primera_vez ───────────────────────────────────────────────────
Route::post('/logout',          [AuthController::class, 'logout'])->middleware('auth:empleado')->name('logout');
Route::post('/password/change', [AuthController::class, 'changePassword'])->middleware('auth:empleado')->name('password.change');

// ─── Notificaciones — sin primera_vez ────────────────────────────────────────
Route::middleware(['auth:empleado', 'role:1,2,3,4'])->group(function () {
    Route::get('/api/notificaciones/mantenimiento', [MantenimientoController::class, 'notificaciones'])
        ->name('notificaciones.mant');
});

// ─── Empleado (roles 1-4) ─────────────────────────────────────────────────────
Route::middleware(['auth:empleado', 'role:1,2,3,4', 'maintenance', 'timeout', 'primera_vez'])->group(function () {
    Route::get('/dashboard', fn() => view('users'))->name('users');

    Route::prefix('api/reservas')->name('reservas.')->group(function () {
        Route::get('/',               [ReservaController::class, 'misSolicitudes'])->name('index');
        Route::post('/',              [ReservaController::class, 'store'])->name('store');
        Route::delete('/{id}',        [ReservaController::class, 'cancelar'])->name('cancelar');
        Route::post('/calcular',      [ReservaController::class, 'calcularFechas'])->name('calcular');
        Route::get('/tipos-catalogo', [ReservaController::class, 'tiposCatalogo'])
            ->middleware('throttle:60,1')
            ->name('tipos.catalogo');
        Route::get('/{id}/historial', [ReservaController::class, 'historial'])->name('historial');
    });
});

// ─── Supervisor (roles 2-4) ────────────────────────────────────────────────────
Route::middleware(['auth:empleado', 'role:2,3,4', 'maintenance', 'timeout', 'primera_vez'])->group(function () {
    Route::get('/supervisor', fn() => view('sup_user'))->name('sup_user');

    Route::prefix('api/supervisor')->name('supervisor.')->group(function () {
        Route::get('/kpis',         [SupervisorController::class, 'kpis'])->name('kpis');
        Route::get('/equipo',       [SupervisorController::class, 'solicitudesEquipo'])->name('equipo');
        Route::put('/evaluar/{id}', [SupervisorController::class, 'evaluar'])->name('evaluar');
        Route::get('/mi-grupo',     [SupervisorController::class, 'miGrupo']);
    });
});

// ─── Admin RH y SuperAdmin (roles 3-4) ────────────────────────────────────────
Route::middleware(['auth:empleado', 'role:3,4', 'maintenance', 'timeout', 'primera_vez'])->group(function () {
    Route::get('/admin',           fn() => view('admin'))->name('admin');
    Route::get('/personal',        fn() => view('personal'))->name('personal');
    Route::get('/grupos',          fn() => view('grupos'))->name('grupos');
    Route::get('/dias-especiales', fn() => view('dias_especiales'))->name('dias_especiales');

    // ── Admin ──────────────────────────────────────────────────────────────────
    Route::prefix('api/admin')->name('admin.')->group(function () {
        Route::get('/kpis',                    [AdminController::class, 'kpis'])->middleware('throttle:30,1')->name('kpis');// máximo 30 requests por minuto por IP
        Route::get('/reservas',                [AdminController::class, 'index'])->name('reservas');
        Route::put('/reservas/{id}',           [AdminController::class, 'update'])->name('reservas.update');
        Route::delete('/reservas/{id}',        [AdminController::class, 'destroy'])->name('reservas.destroy');
        Route::delete('/reservas/{id}/hard',   [AdminController::class, 'hardDestroy'])->name('reservas.hard');
        Route::get('/reservas/{id}/historial', [AdminController::class, 'historial'])->name('reservas.historial');
        Route::get('/exportar',                [AdminController::class, 'exportar'])->name('exportar');
    });

    // ── Personal ───────────────────────────────────────────────────────────────
    Route::prefix('api/personal')->name('personal.')->group(function () {
        Route::get('/',                        [PersonalController::class, 'index'])->name('index');
        Route::get('/bloqueados',              [PersonalController::class, 'bloqueados'])->name('bloqueados');
        Route::get('/ips-bloqueadas',          [PersonalController::class, 'ipsBloqueadas'])->name('ips');
        Route::delete('/ips-bloqueadas/{ip}',  [PersonalController::class, 'desbloquearIp'])->name('ips.desbloquear');
        Route::delete('/{nomina}/hard',        [PersonalController::class, 'hardDestroyEmpleado'])->name('hard');
        Route::put('/{nomina}/rol',            [PersonalController::class, 'updateRol'])->name('rol');
        Route::put('/{nomina}/reset-password', [PersonalController::class, 'resetPassword'])->name('reset');
        Route::delete('/{nomina}/desactivar',  [PersonalController::class, 'desactivar'])->name('desactivar');
        Route::put('/{nomina}/reactivar',      [PersonalController::class, 'reactivar'])->name('reactivar');
        Route::put('/{nomina}/desbloquear',    [PersonalController::class, 'desbloquearUsuario'])->name('desbloquear');
    });

    // ── Días especiales ────────────────────────────────────────────────────────
    Route::prefix('api/dias-especiales')->name('dias.')->group(function () {
        Route::get('/',                    [DiasEspecialesController::class, 'index'])->name('index');
        Route::post('/',                   [DiasEspecialesController::class, 'store'])->name('store');
        // Rutas literales ANTES de las con {id}
        Route::get('/centros',             [DiasEspecialesController::class, 'centros'])->name('centros');
        Route::post('/centros',            [DiasEspecialesController::class, 'guardarCentro'])->name('centros.save');
        Route::delete('/centros/{centro}', [DiasEspecialesController::class, 'eliminarCentro'])->name('centros.delete');
        Route::delete('/{id}/hard',        [DiasEspecialesController::class, 'hardDestroyDia'])->name('hard');
        Route::put('/{id}',                [DiasEspecialesController::class, 'update'])->name('update');
        Route::patch('/{id}/toggle',       [DiasEspecialesController::class, 'toggleActivo'])->name('toggle');
    });

    // ── Quincenas ──────────────────────────────────────────────────────────────
    Route::prefix('api/quincenas')->name('quincenas.')->group(function () {
        Route::get('/',              [QuincenaController::class, 'index'])->name('index');
        Route::post('/',             [QuincenaController::class, 'store'])->name('store');
        // 'generar-anio' ANTES de {id} para que no colisione
        Route::post('/generar-anio', [QuincenaController::class, 'generarAnio'])->name('generar');
        Route::put('/{id}',          [QuincenaController::class, 'update'])->name('update');
        Route::patch('/{id}/toggle', [QuincenaController::class, 'toggle'])->name('toggle');
        Route::delete('/{id}/hard',  [QuincenaController::class, 'hardDestroy'])->name('hard');
    });

    // ── Tipos de solicitud ─────────────────────────────────────────────────────
    Route::prefix('api/tipos-solicitud')->name('tipos.')->group(function () {
        Route::get('/',              [TipoSolicitudController::class, 'index'])->name('index');
        Route::post('/',             [TipoSolicitudController::class, 'store'])->name('store');
        Route::put('/{id}',          [TipoSolicitudController::class, 'update'])->name('update');
        Route::patch('/{id}/toggle', [TipoSolicitudController::class, 'toggle'])->name('toggle');
        // Sin hard delete — los tipos tienen historial en reservas
    });

    // ── Grupos ─────────────────────────────────────────────────────────────────
    Route::prefix('api/grupos')->name('grupos.')->group(function () {
        Route::get('/',                          [GrupoController::class, 'index'])->name('index');
        Route::post('/',                         [GrupoController::class, 'store'])->name('store');
        Route::get('/buscar-empleados',          [GrupoController::class, 'buscarEmpleados'])->name('buscar');
        Route::get('/{id}',                      [GrupoController::class, 'show'])->name('show');
        Route::delete('/{id}',                   [GrupoController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/supervisor',           [GrupoController::class, 'cambiarSupervisor'])->name('supervisor');
        Route::post('/{id}/miembros',            [GrupoController::class, 'agregarMiembro'])->name('miembro.add');
        Route::delete('/{id}/miembros/{nomina}', [GrupoController::class, 'quitarMiembro'])->name('miembro.remove');
        Route::post('/{id}/importar',            [GrupoController::class, 'importarMasivo'])->name('importar');
    });
});

// ─── SuperAdmin (rol 4) ────────────────────────────────────────────────────────
Route::middleware(['auth:empleado', 'role:4', 'timeout', 'primera_vez'])->group(function () {
    Route::get('/mantenimiento', fn() => view('maintenance'))->name('maintenance');

    Route::prefix('api/auditoria')->name('auditoria.')->group(function () {
        Route::get('/',         [AuditoriaController::class, 'index'])->name('index');
        Route::get('/acciones', [AuditoriaController::class, 'acciones'])->name('acciones');
        Route::get('/exportar', [AuditoriaController::class, 'exportar'])->name('exportar');
    });

    Route::prefix('api/mantenimiento')->name('mant.')->group(function () {
        Route::get('/',                [MantenimientoController::class, 'index'])->name('index');
        Route::get('/estado',          [MantenimientoController::class, 'estado'])->name('estado');
        Route::post('/',               [MantenimientoController::class, 'store'])->name('store');
        Route::put('/{id}/activar',    [MantenimientoController::class, 'activar'])->name('activar');
        Route::put('/{id}/detener',    [MantenimientoController::class, 'detener'])->name('detener');
        Route::put('/{id}/cancelar',   [MantenimientoController::class, 'cancelar'])->name('cancelar');
        Route::delete('/{id}',         [MantenimientoController::class, 'destroy'])->name('destroy');
        Route::post('/importar-excel', [MantenimientoController::class, 'importarExcel'])->name('importar');
        Route::get('/backup',          [MantenimientoController::class, 'generarBackup'])->name('backup');
        Route::post('/restaurar',      [MantenimientoController::class, 'restaurarBackup'])->name('restaurar');
        Route::post('/reiniciar',      [MantenimientoController::class, 'reiniciar'])->name('reiniciar');
    });
});


// Health check — sin autenticación, accesible por sistemas de monitoreo
Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        return response()->json([
            'status'   => 'ok',
            'database' => 'ok',
            'time'     => now()->toISOString(),
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status'   => 'error',
            'database' => 'unreachable',
        ], 500);
    }
})->name('health');