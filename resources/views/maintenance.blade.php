@extends('layouts.app')
@section('title', 'Mantenimiento — Canel\'s')

@section('content')
@php $emp = Auth::guard('empleado')->user(); @endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Agenda de Mantenimiento</h1>
        <div class="h-1 w-16 bg-amber-500 mt-1 rounded-full"></div>
        <p class="text-sm text-gray-500 mt-1">Panel exclusivo del SuperAdmin para gestionar el sistema.</p>
    </div>
    {{-- Banner modo mantenimiento --}}
    <div id="bannerMant" class="hidden bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg animate-pulse">
        <i class="fas fa-tools"></i> SISTEMA EN MANTENIMIENTO
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- ── COLUMNA IZQUIERDA ── --}}
    <div class="lg:col-span-8 space-y-6">

        {{-- Formulario reservar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-primary"></i> Programar Mantenimiento
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Categoría</label>
                    <select id="newCategoria"
                            class="w-full bg-gray-50 border border-gray-300 text-gray-700 py-2.5 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="" disabled selected>Seleccionar...</option>
                        <option value="Mantenimiento Correctivo">Mantenimiento Correctivo</option>
                        <option value="Mantenimiento Preventivo">Mantenimiento Preventivo</option>
                        <option value="Actualización Evolutiva">Actualización Evolutiva</option>
                        <option value="Respaldo de Base de Datos">Respaldo de Base de Datos</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha y Hora Inicio</label>
                    <input type="datetime-local" id="newInicio"
                           class="w-full bg-gray-50 border border-gray-300 text-gray-700 py-2.5 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha y Hora Fin</label>
                    <input type="datetime-local" id="newFin"
                           class="w-full bg-gray-50 border border-gray-300 text-gray-700 py-2.5 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Notas / Recomendaciones</label>
                    <input type="text" id="newNotas" placeholder="Ej: Realizar respaldo previo..."
                           class="w-full bg-gray-50 border border-gray-300 text-gray-700 py-2.5 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>
            <div id="formMantMsg" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
            <div class="mt-4 flex justify-end">
                <button onclick="programarMantenimiento()"
                        class="bg-primary hover:bg-blue-900 text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i> Reservar
                </button>
            </div>
        </div>

        {{-- Bitácora --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-history text-gray-400"></i> Bitácora Programada
                </h2>
                <button onclick="cargarBitacora()"
                        class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                    <i class="fas fa-sync-alt text-xs"></i>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-primary text-white text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3">Categoría</th>
                            <th class="px-4 py-3">Fecha Inicio</th>
                            <th class="px-4 py-3">Fecha Fin</th>
                            <th class="px-4 py-3 max-w-xs">Notas</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaBitacora" class="divide-y divide-gray-100">
                        <tr><td colspan="6" class="py-8 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin text-primary mr-2"></i>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── COLUMNA DERECHA: Acciones críticas ── --}}
    <div class="lg:col-span-4 space-y-5">

        {{-- Estado del servidor --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4 flex items-center gap-2">
                <i class="fas fa-server text-green-500"></i> Estado del Sistema
            </h3>
            <div class="space-y-2">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600">Base de Datos</span>
                    <span id="dbStatus" class="text-xs px-2 py-1 rounded font-bold bg-gray-100 text-gray-500">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600">Último Mantenimiento</span>
                    <span id="lastMaint" class="text-xs text-gray-500">—</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600">Modo Actual</span>
                    <span id="modoActual" class="text-xs px-2 py-1 rounded font-bold bg-green-100 text-green-700">Normal</span>
                </div>
            </div>
            <button onclick="cargarEstado()"
                    class="w-full mt-4 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold py-2 px-4 rounded-lg transition">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar Estado
            </button>
        </div>

        {{-- Importar Excel --}}
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-teal-500 p-5">
            <h3 class="text-base font-bold text-teal-600 mb-2 flex items-center gap-2">
                <i class="fas fa-file-excel"></i> Importar Empleados (Excel)
            </h3>
            <p class="text-xs text-gray-500 mb-3">
                Lee columnas: <code class="bg-gray-100 px-1 rounded">NUM NOMINA</code>,
                <code class="bg-gray-100 px-1 rounded">NOMBRE</code>,
                <code class="bg-gray-100 px-1 rounded">PriVac</code>,
                <code class="bg-gray-100 px-1 rounded">Centro de Pago</code>
            </p>

            <div class="mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase mb-1 block">Si el empleado ya existe:</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                        <input type="radio" name="modoImport" value="agregar" checked class="text-teal-600"> Agregar / Actualizar
                    </label>
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                        <input type="radio" name="modoImport" value="reemplazar" class="text-red-500"> Reemplazar todo
                    </label>
                </div>
            </div>

            <label id="excelLabel"
                   class="w-full cursor-not-allowed flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-400 rounded-lg border-2 border-dashed border-gray-300 mb-3 opacity-50 transition">
                <i class="fas fa-file-excel mr-2 text-green-400 text-xl"></i>
                <span id="excelFileName">Sistema en línea — requiere mantenimiento</span>
                <input type="file" id="excelFile" class="hidden" accept=".xlsx,.xls" disabled
                       onchange="document.getElementById('excelFileName').textContent = this.files[0]?.name ?? 'Seleccionar archivo'">
            </label>

            <div id="excelMsg" class="hidden mb-3 p-3 rounded-lg text-xs"></div>

            <button id="btnImportar" onclick="importarExcel()" disabled
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-upload mr-1"></i> Importar Datos
            </button>
        </div>

        {{-- Backup --}}
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-indigo-500 p-5">
            <h3 class="text-base font-bold text-indigo-600 mb-2 flex items-center gap-2">
                <i class="fas fa-download"></i> Generar Copia de Seguridad
            </h3>
            <p class="text-xs text-gray-500 mb-4">Descarga un archivo <code>.sql</code> con todos los datos actuales del sistema.</p>
            <button onclick="descargarBackup()"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm shadow-sm transition">
                <i class="fas fa-database mr-1"></i> Descargar Respaldo SQL
            </button>
        </div>

        {{-- Restaurar --}}
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5">
            <h3 class="text-base font-bold text-blue-600 mb-2 flex items-center gap-2">
                <i class="fas fa-history"></i> Restaurar Respaldo
            </h3>
            <p class="text-xs text-gray-500 mb-3">Sube un archivo <code>.sql</code> generado previamente. <strong class="text-blue-700">Requiere mantenimiento activo.</strong></p>

            <label id="sqlLabel"
                   class="w-full cursor-not-allowed flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-400 rounded-lg border-2 border-dashed border-gray-300 mb-3 opacity-50 transition">
                <i class="fas fa-file-code mr-2 text-blue-400 text-xl"></i>
                <span id="sqlFileName">Requiere modo mantenimiento</span>
                <input type="file" id="sqlFile" class="hidden" accept=".sql" disabled
                       onchange="document.getElementById('sqlFileName').textContent = this.files[0]?.name ?? 'Seleccionar archivo'">
            </label>

            <div id="sqlMsg" class="hidden mb-3 p-3 rounded-lg text-xs"></div>

            <button id="btnRestaurar" onclick="restaurarBackup()" disabled
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-upload mr-1"></i> Restaurar Base de Datos
            </button>
        </div>

        {{-- Auditoría --}}
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-violet-500 p-5">
            <h3 class="text-base font-bold text-violet-600 mb-2 flex items-center gap-2">
                <i class="fas fa-shield-alt"></i> Auditoría del Sistema
            </h3>
            <p class="text-xs text-gray-500 mb-3">
                Historial de acciones. Disponible sin necesitar modo mantenimiento.
            </p>

            {{-- Filtros rápidos --}}
            <div class="space-y-2 mb-3">
                <select id="audAccion"
                        class="w-full border border-gray-200 rounded-lg p-2 text-xs text-gray-700 focus:ring-1 focus:ring-violet-400 outline-none bg-gray-50">
                    <option value="">Todas las acciones</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-0.5">Desde</label>
                        <input type="date" id="audDesde"
                               class="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-violet-400 outline-none bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-0.5">Hasta</label>
                        <input type="date" id="audHasta"
                               class="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-violet-400 outline-none bg-gray-50">
                    </div>
                </div>
                <input type="text" id="audBuscar" placeholder="Buscar nómina, acción..."
                       class="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-violet-400 outline-none bg-gray-50">
            </div>

            {{-- Mini tabla --}}
            <div id="audTabla" class="border border-gray-100 rounded-lg overflow-hidden mb-3 max-h-[200px] overflow-y-auto text-xs">
                <div class="py-4 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Cargando...
                </div>
            </div>

            <p id="audInfo" class="text-[10px] text-gray-400 mb-3"></p>

            <div class="flex gap-2">
                <button onclick="cargarAuditoria(1)"
                        class="flex-1 bg-violet-100 hover:bg-violet-200 text-violet-700 font-semibold py-2 rounded-lg text-xs transition">
                    <i class="fas fa-search mr-1"></i> Buscar
                </button>
                <button onclick="exportarAuditoria()"
                        class="flex-1 bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2 rounded-lg text-xs transition">
                    <i class="fas fa-file-excel mr-1"></i> Descargar Excel
                </button>
            </div>
        </div>

        {{-- Reiniciar sistema --}}
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-5">
            <h3 class="text-base font-bold text-red-600 mb-2 flex items-center gap-2">
                <i class="fas fa-skull-crossbones"></i> Reiniciar Sistema
            </h3>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <p class="text-xs text-red-800 font-bold mb-1">⚠️ ZONA DE PELIGRO</p>
                <p class="text-xs text-gray-600">Elimina <strong>todos los datos</strong> de empleados, reservas, grupos e historial. El SuperAdmin permanece. Esta acción es irreversible.</p>
            </div>
            <button id="btnReiniciar" onclick="openModal('reiniciarModal')" disabled
                    class="w-full bg-white border-2 border-red-500 text-red-600 hover:bg-red-500 hover:text-white font-bold py-2.5 px-4 rounded-xl text-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-trash-alt mr-1"></i> Limpiar Todo
            </button>
        </div>
    </div>
</div>

{{-- ── MODAL CONFIRMAR REINICIO ── --}}
<div id="reiniciarModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('reiniciarModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 border-t-8 border-red-600">
            <div class="text-center mb-5">
                <div class="h-16 w-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-1">¿Estás completamente seguro?</h3>
                <p class="text-sm text-gray-500">Esta acción eliminará <strong>todos los datos</strong> del sistema y no puede deshacerse.</p>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tu contraseña (confirmar identidad)</label>
                <input type="password" id="pwdReiniciar"
                       class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-red-500 outline-none"
                       placeholder="Ingresa tu contraseña">
            </div>
            <div id="reiniciarMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <div class="flex flex-col gap-2">
                <button onclick="confirmarReinicio()"
                        class="w-full px-4 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition">
                    Sí, Eliminar Todo
                </button>
                <button onclick="closeModal('reiniciarModal')"
                        class="w-full px-4 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/maintenance.js'])
@endpush