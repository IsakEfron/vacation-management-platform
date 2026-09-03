@extends('layouts.app')
@section('title', 'Gestión de Vacaciones — Canel\'s')

@section('content')

{{-- ── Encabezado ── --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Vacaciones</h1>
        <div class="h-1 w-16 bg-amber-500 mt-1 rounded-full"></div>
        <p class="text-sm text-gray-500 mt-1">Administra y aprueba las solicitudes de los empleados.</p>
    </div>
    <div class="text-xs text-gray-400 bg-white border border-gray-200 rounded-xl px-4 py-2 shadow-sm">
        <i class="fas fa-calendar-alt text-primary mr-1"></i>
        {{ now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }}
    </div>
</div>

{{-- ── KPIs con filtro de rango ── --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 mb-4 flex flex-wrap items-center gap-3">
    <span class="text-xs font-bold text-gray-500 uppercase tracking-wide shrink-0">Periodo:</span>

    {{-- Botones rápidos --}}
    <div class="flex gap-1 flex-wrap">
        @foreach(['semana'=>'Semana','quincena'=>'Quincena','mes'=>'Mes','año'=>'Año','todo'=>'Total'] as $key=>$label)
        <button onclick="setRango('{{ $key }}')" data-rango="{{ $key }}"
                class="rango-btn px-3 py-1.5 text-xs font-semibold rounded-lg border transition
                    {{ $key === 'mes' ? 'bg-primary text-white border-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Rango personalizado --}}
    <div class="flex items-center gap-2 ml-auto flex-wrap">
        <input type="date" id="kpiDesde" placeholder="Desde"
               class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700 focus:ring-1 focus:ring-primary outline-none">
        <span class="text-gray-400 text-xs">al</span>
        <input type="date" id="kpiHasta" placeholder="Hasta"
               class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700 focus:ring-1 focus:ring-primary outline-none">
        <button onclick="setRango('personalizado')"
                class="px-3 py-1.5 bg-gray-100 hover:bg-primary hover:text-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-200 transition">
            Aplicar
        </button>
    </div>

    {{-- Etiqueta del periodo activo --}}
    <div class="w-full pt-1 border-t border-gray-100 flex items-center gap-2">
        <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
        <span id="rangoLabel" class="text-xs text-gray-500 italic">Cargando...</span>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-yellow-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Pendientes</p>
        <p id="kpi-pendientes" class="text-3xl font-bold text-gray-800 mt-1">—</p>
        <p class="text-xs text-yellow-600 mt-1"><i class="fas fa-clock mr-1"></i>Esperando revisión</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-blue-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Visto Bueno</p>
        <p id="kpi-visto" class="text-3xl font-bold text-gray-800 mt-1">—</p>
        <p class="text-xs text-blue-600 mt-1"><i class="fas fa-check mr-1"></i>Listos para aprobar</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-green-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Aprobadas</p>
        <p id="kpi-aprobadas" class="text-3xl font-bold text-gray-800 mt-1">—</p>
        <p class="text-xs text-green-600 mt-1"><i class="fas fa-check-double mr-1"></i>Confirmadas</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-red-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Rechazadas</p>
        <p id="kpi-rechazadas" class="text-3xl font-bold text-gray-800 mt-1">—</p>
        <p class="text-xs text-red-600 mt-1"><i class="fas fa-times mr-1"></i>Total rechazadas</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-primary">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Empleados</p>
        <p id="kpi-empleados" class="text-3xl font-bold text-gray-800 mt-1">—</p>
        <p class="text-xs text-primary mt-1"><i class="fas fa-users mr-1"></i>Activos</p>
    </div>
</div>

{{-- ── Tabla ── --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Filtros — diseño responsive de 2 filas --}}
    <div class="p-4 border-b border-gray-100">

        {{-- Fila 1: Título + búsqueda --}}
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 shrink-0">
                <h2 class="text-base font-bold text-gray-800">Solicitudes</h2>
                <span id="badgePendientes"
                    class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2.5 py-0.5 rounded-full border border-yellow-200">
                    cargando...
                </span>
            </div>

            {{-- Búsqueda — crece para llenar el espacio disponible --}}
            <div class="relative flex-1 max-w-xs min-w-0">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                <input type="text" id="buscarInput" placeholder="Nómina o nombre..."
                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm
                            focus:outline-none focus:ring-1 focus:ring-primary">
            </div>
        </div>

        {{-- Fila 2: Filtro de estado + acciones --}}
        <div class="flex flex-wrap items-center gap-2">

            {{-- Filtro de estado --}}
            <select id="filtroEstado"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white text-gray-700
                        focus:outline-none focus:ring-1 focus:ring-primary flex-1 min-w-[160px] max-w-[220px]">
                <option value="">Todos los estados</option>
                <option value="1">Pendiente</option>
                <option value="2">Visto Bueno</option>
                <option value="4">Aprobada</option>
                <option value="3">Rechazada por Supervisor</option>
                <option value="5">Rechazada por RH</option>
                <option value="6">Cancelada</option>
            </select>

            {{-- Separador visual --}}
            <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>

            {{-- Botón buscar --}}
            <button onclick="buscar()"
                    class="bg-primary hover:bg-blue-900 text-white px-4 py-2 rounded-lg text-sm
                        font-semibold transition flex items-center gap-1.5 whitespace-nowrap">
                <i class="fas fa-search text-xs"></i>
                <span>Buscar</span>
            </button>

            {{-- Botón limpiar filtros --}}
            <button onclick="limpiarFiltros()"
                    class="border border-gray-200 text-gray-500 hover:bg-gray-50 px-3 py-2 rounded-lg
                        text-sm font-semibold transition flex items-center gap-1.5 whitespace-nowrap"
                    title="Limpiar filtros">
                <i class="fas fa-times text-xs"></i>
                <span class="hidden sm:inline">Limpiar</span>
            </button>

            {{-- Spacer para empujar Exportar a la derecha en pantallas grandes --}}
            <div class="flex-1 hidden sm:block"></div>

            {{-- Botón exportar --}}
            <button onclick="abrirExportar()"
                    class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white
                        px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap ml-auto sm:ml-0">
                <i class="fas fa-file-excel text-sm"></i>
                <span>Exportar Excel</span>
            </button>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Empleado / Nómina</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Fechas</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Observación</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaReservas" class="bg-white divide-y divide-gray-100">
                <tr><td colspan="6" class="py-10 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin text-xl mb-2 block"></i>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-2">
        <p id="paginacionInfo" class="text-xs text-gray-500"></p>
        <div id="paginacionBtns" class="flex gap-1"></div>
    </div>
</div>

{{-- ── MODAL EDITAR ── --}}
<div id="editModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('editModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
            <div class="px-6 pt-5 pb-3 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Editar Solicitud</h3>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Empleado</label>
                        <input type="text" id="editNombre" disabled
                               class="w-full border border-gray-200 bg-gray-50 rounded-lg p-2.5 text-sm text-gray-700">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nómina</label>
                        <input type="text" id="editNomina" disabled
                               class="w-full border border-gray-200 bg-gray-50 rounded-lg p-2.5 text-sm text-gray-700">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fechas</label>
                    <input type="text" id="editFechas" disabled
                           class="w-full border border-gray-200 bg-gray-50 rounded-lg p-2.5 text-sm text-gray-700">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Estado</label>
                    <select id="editEstado"
                            class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none">
                        <option value="1">Pendiente</option>
                        <option value="2">Visto Bueno (Supervisor)</option>
                        <option value="4">Aprobada</option>
                        <option value="3">Rechazada por Supervisor</option>
                        <option value="5">Rechazada por RH</option>
                        <option value="6">Cancelada</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observación</label>
                    <textarea id="editObs" rows="3"
                              class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none resize-none"
                              placeholder="Agrega un comentario..."></textarea>
                </div>
                <div id="editMsg" class="hidden p-3 rounded-lg text-sm"></div>
            </div>
            <div class="px-6 pb-5 flex justify-end gap-3">
                <button onclick="closeModal('editModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="guardarEdicion()"
                        class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold shadow-sm transition">
                    Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL EXPORTAR ── --}}
<div id="exportModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('exportModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-6 pt-5 pb-3 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-file-excel text-emerald-600"></i> Exportar Excel
                </h3>
                <p class="text-xs text-gray-400 mt-1">Configura el periodo antes de exportar.</p>
            </div>
            <div class="px-6 py-5 space-y-4">

                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    El archivo tendrá <strong>2 hojas</strong>: Reporte RH y TREESS-ASCII.
                </div>

                {{-- Año --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Año de nómina</label>
                    <input type="number" id="exportAnio" value="{{ now()->year }}" min="2020" max="2099"
                           onchange="cargarQuincenasExport()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-primary outline-none">
                </div>

                {{-- Selector de quincena — se rellena dinámicamente --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">
                        Quincena
                        <span id="exportQBadge"
                              class="ml-1 normal-case font-normal text-gray-400 text-xs"></span>
                    </label>

                    {{-- Estado: cargando --}}
                    <div id="exportQLoading" class="hidden text-xs text-gray-400 py-2">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Buscando quincenas registradas...
                    </div>

                    {{-- Select con quincenas de BD --}}
                    <select id="exportQSelect"
                            class="hidden w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-primary outline-none">
                    </select>

                    {{-- Fallback: input manual (cuando no hay quincenas en BD) --}}
                    <div id="exportQManualWrap" class="hidden">
                        <input type="number" id="exportQuincena"
                               value="{{ ceil(now()->month * 2 - (now()->day <= 15 ? 1 : 0)) }}"
                               min="1" max="24" placeholder="1-24"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-primary outline-none">
                        <p class="text-xs text-amber-600 mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No hay quincenas registradas para este año en la BD.
                            <a href="/dias-especiales" class="underline" target="_blank">Registrarlas aquí</a>
                            o ingresa el número manualmente.
                        </p>
                    </div>
                </div>

                <div id="exportMsg" class="hidden p-3 rounded-lg text-sm"></div>
            </div>
            <div class="px-6 pb-5 flex justify-end gap-3">
                <button onclick="closeModal('exportModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button onclick="confirmarExportar()"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class="fas fa-download"></i> Descargar Excel
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL HISTORIAL ── --}}
<div id="historyModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('historyModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
            <div class="bg-gray-50 px-5 py-4 border-b border-gray-200 flex justify-between items-center rounded-t-xl">
                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ph ph-clock-counter-clockwise text-primary text-lg"></i> Historial de Cambios
                </h3>
                <button onclick="closeModal('historyModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div id="historialContenido" class="px-6 py-6 min-h-[120px]">
                <p class="text-center text-gray-400 text-sm">Cargando...</p>
            </div>
            <div class="bg-gray-50 px-5 py-3 rounded-b-xl flex justify-end">
                <button onclick="closeModal('historyModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL ELIMINAR / CANCELAR ── --}}
<div id="deleteModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('deleteModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="px-6 pt-6 pb-3 text-center">
                <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-warning text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Gestionar Solicitud</h3>
                <p id="deleteNombreLabel" class="text-sm text-gray-500 mb-4"></p>
            </div>

            {{-- Opción 1: Cancelar (devuelve saldo) --}}
            <div class="mx-4 mb-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <p class="text-xs font-bold text-amber-700 mb-1"><i class="fas fa-undo mr-1"></i> Cancelar solicitud</p>
                <p class="text-xs text-gray-500 mb-2">Cambia el estado a <strong>Cancelada</strong>. Si había saldo descontado, <strong>se devuelve</strong> al empleado. Queda en el historial.</p>
                <button id="btnCancelarSolicitud"
                        class="w-full px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg text-sm transition">
                    Cancelar (devolver saldo)
                </button>
            </div>

            {{-- Opción 2: Eliminar definitivo (solo SuperAdmin) --}}
            @php $adminEmp = Auth::guard('empleado')->user(); @endphp
            @if($adminEmp->rol == 4)
            <div class="mx-4 mb-3 p-3 bg-red-50 border border-red-200 rounded-xl">
                <p class="text-xs font-bold text-red-700 mb-1"><i class="fas fa-skull-crossbones mr-1"></i> Eliminar permanentemente</p>
                <p class="text-xs text-gray-500 mb-2">Borra el registro y su historial <strong>de forma irreversible</strong>. No devuelve saldo. Solo usar para datos incorrectos.</p>
                <button id="btnEliminarDefinitivo"
                        class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-sm transition">
                    Eliminar permanentemente
                </button>
            </div>
            @endif

            <div class="px-4 pb-4">
                <button onclick="closeModal('deleteModal')"
                        class="w-full px-4 py-2 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition text-sm">
                    Regresar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/admin.js'])
@endpush