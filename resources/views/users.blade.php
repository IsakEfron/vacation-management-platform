@extends('layouts.app')
@section('title', 'Mis Vacaciones — Canel\'s')

@section('content')
@php $emp = Auth::guard('empleado')->user(); @endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    {{-- ── PANEL IZQUIERDO: Agendar solicitud ── --}}
    <div class="lg:col-span-4 space-y-5">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">

            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph ph-calendar-blank text-primary"></i> Agendar Fechas
                </h2>
                <div class="h-1 w-12 bg-amber-500 mt-1 rounded-full"></div>
            </div>

            {{-- Saldo --}}
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200 mb-5 text-center">
                <p class="text-xs text-blue-600 uppercase tracking-widest font-semibold mb-1">Saldo Vacacional</p>
                <p id="saldoDisplay" class="font-black text-gray-800 text-4xl">{{ $emp->saldo }}</p>
                <p class="text-xs text-blue-400 mt-1">días disponibles</p>
            </div>

            {{-- Fechas --}}
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Inicio</label>
                    <input type="date" id="fechaInicio"
                           min="{{ now()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fin</label>
                    <input type="date" id="fechaFin"
                           min="{{ now()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                </div>
            </div>

            {{-- Preview dinámico --}}
            <div id="previewCard" class="hidden bg-blue-50 rounded-xl p-4 border border-blue-100 mb-4">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Inicio</p>
                        <p id="prevInicio" class="font-bold text-gray-800 text-sm"></p>
                    </div>
                    <div class="border-x border-blue-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Fin</p>
                        <p id="prevFin" class="font-bold text-gray-800 text-sm"></p>
                    </div>
                    <div>
                        <p class="text-xs text-primary font-bold uppercase tracking-wide mb-1">Regreso</p>
                        <p id="prevRegreso" class="font-bold text-primary text-sm"></p>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-blue-200 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Días hábiles:</span>
                    <span id="prevDias" class="text-sm font-bold text-gray-800 bg-white px-3 py-0.5 rounded-full border border-blue-200">—</span>
                </div>
                {{-- Advertencia de saldo --}}
                <div id="saldoWarning" class="hidden mt-2 p-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="saldoWarningText"></span>
                </div>
            </div>

            {{-- Tipo de permiso --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">Tipo de Permiso</label>
                <div class="relative">
                    <select id="tipoSolicitud"
                            class="w-full appearance-none bg-gray-50 border border-gray-200 text-gray-700
                                py-2.5 px-4 pr-8 rounded-lg text-sm focus:outline-none focus:bg-white
                                focus:border-primary focus:ring-2 focus:ring-primary/20 transition">
                        <option value="" disabled selected>Cargando tipos...</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3">
                        <i class="ph ph-caret-down text-gray-400"></i>
                    </div>
                </div>
                {{-- Indicador si descuenta saldo --}}
                <p id="saldoIndicador" class="hidden text-xs text-amber-600 mt-1 flex items-center gap-1">
                    <i class="fas fa-info-circle"></i> Este permiso descuenta días de tu saldo vacacional.
                </p>
            </div>

            {{-- Observaciones opcionales --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5">
                    Observaciones <span class="text-gray-400 normal-case font-normal">(opcional)</span>
                </label>
                <textarea id="observaciones" rows="2"
                          class="w-full border border-gray-200 rounded-lg p-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition resize-none"
                          placeholder="Ej: Viaje familiar, boda..."></textarea>
            </div>

            {{-- Mensaje de error/éxito --}}
            <div id="formMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>

            <button id="btnSolicitar" onclick="solicitarFechas()"
                    class="w-full bg-primary hover:bg-blue-900 text-white font-semibold py-3 px-4 rounded-xl shadow-sm transition-all flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="ph ph-paper-plane-right text-lg"></i>
                <span id="btnText">Enviar Solicitud</span>
            </button>
        </div>
    </div>

    {{-- ── PANEL DERECHO: Mis solicitudes ── --}}
    <div class="lg:col-span-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Mis Solicitudes</h2>
                    <div class="h-1.5 w-16 bg-amber-500 mt-1"></div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-blue-50 text-primary text-xs font-bold rounded-full border border-blue-100">
                        {{ date('Y') }}
                    </span>
                    <button onclick="toggleCanceladas()"
                            id="btnToggleCanceladas"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                            title="Mostrar/ocultar canceladas">
                        <i class="ph ph-eye-slash" id="iconToggle"></i>
                        <span id="lblToggle">Ocultar canceladas</span>
                    </button>
                    <button onclick="cargarSolicitudes()"
                            class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                        <i class="fas fa-sync-alt text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase text-gray-400 font-bold tracking-wider">
                            <th class="px-5 py-3">Fechas</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3 text-center">Días</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaSolicitudes" class="divide-y divide-gray-100">
                        <tr id="loadingRow">
                            <td colspan="5" class="px-5 py-10 text-center">
                                <i class="fas fa-spinner fa-spin text-primary text-xl mb-2 block"></i>
                                <span class="text-sm text-gray-400">Cargando solicitudes...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL CANCELAR ── --}}
<div id="deleteModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('deleteModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-warning text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">¿Cancelar solicitud?</h3>
            <p class="text-sm text-gray-500 mb-1">El saldo descontado será <strong>devuelto</strong> automáticamente.</p>
            <p class="text-xs text-gray-400 mb-6">Solo puedes cancelar solicitudes en estado Pendiente.</p>
            <div class="flex flex-col gap-2">
                <button id="btnConfirmarCancelar"
                        class="w-full px-4 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition">
                    Sí, cancelar
                </button>
                <button onclick="closeModal('deleteModal')"
                        class="w-full px-4 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">
                    Regresar
                </button>
            </div>
        </div>
    </div>
</div>


{{-- ── MODAL HISTORIAL ── --}}
<div id="historialModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('historialModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="bg-gray-50 px-5 py-4 border-b border-gray-200 flex justify-between items-center rounded-t-2xl">
                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ph ph-clock-counter-clockwise text-primary text-lg"></i>
                    Historial de la Solicitud
                </h3>
                <button onclick="closeModal('historialModal')" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div id="historialContenido" class="px-6 py-6 max-h-[480px] overflow-y-auto">
                <div class="py-6 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin text-primary text-xl mb-2 block"></i>Cargando historial...
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 rounded-b-2xl flex justify-end">
                <button onclick="closeModal('historialModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')

    {{-- 1. Pasamos la variable de PHP a JavaScript de forma global --}}
    <script>
        window.SALDO = {{ $emp->saldo }};
    </script>

    {{-- 2. Usamos la directiva de Vite para cargar los scripts --}}
    @vite([
        'resources/js/tiposSolicitud.js', 
        'resources/js/users.js'
    ])
@endpush