@extends('layouts.app')
@section('title', 'Dashboard Supervisor — Canel\'s')

@section('content')
@php $emp = Auth::guard('empleado')->user(); @endphp

{{-- ── KPIs del equipo ── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Pendientes de equipo</p>
        <p id="kpi-pendientes" class="text-3xl font-black text-gray-800 mt-1">—</p>
        <p class="text-xs text-yellow-600 mt-1"><i class="fas fa-clock mr-1"></i>Esperan tu revisión</p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-400">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Enviadas a RH</p>
        <p id="kpi-enviadas" class="text-3xl font-black text-gray-800 mt-1">—</p>
        <p class="text-xs text-blue-600 mt-1"><i class="fas fa-check mr-1"></i>Visto Bueno otorgado</p>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-primary">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Mi equipo</p>
        <p id="kpi-equipo" class="text-3xl font-black text-gray-800 mt-1">—</p>
        <p class="text-xs text-primary mt-1"><i class="fas fa-users mr-1"></i>Personas a cargo</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- ── IZQUIERDA: Agendar mis propias fechas ── --}}
    <div class="lg:col-span-4 space-y-5">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph ph-calendar-blank text-primary"></i> Mis Fechas
                </h2>
                <div class="h-1 w-12 bg-amber-500 mt-1 rounded-full"></div>
            </div>

            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200 mb-5 text-center">
                <p class="text-xs text-blue-600 uppercase tracking-widest font-semibold mb-1">Saldo Vacacional</p>
                <p id="saldoDisplay" class="font-black text-gray-800 text-4xl">{{ $emp->saldo }}</p>
                <p class="text-xs text-blue-400 mt-1">días disponibles</p>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Inicio</label>
                    <input type="date" id="fechaInicio" min="{{ now()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fin</label>
                    <input type="date" id="fechaFin" min="{{ now()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                </div>
            </div>

            <div id="previewCard" class="hidden bg-blue-50 rounded-xl p-4 border border-blue-100 mb-4">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-xs text-gray-500 uppercase mb-1">Inicio</p>
                        <p id="prevInicio" class="font-bold text-gray-800 text-sm"></p>
                    </div>
                    <div class="border-x border-blue-200">
                        <p class="text-xs text-gray-500 uppercase mb-1">Fin</p>
                        <p id="prevFin" class="font-bold text-gray-800 text-sm"></p>
                    </div>
                    <div>
                        <p class="text-xs text-primary font-bold uppercase mb-1">Regreso</p>
                        <p id="prevRegreso" class="font-bold text-primary text-sm"></p>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-t border-blue-200 flex justify-between items-center text-xs">
                    <span class="text-gray-500">Días hábiles:</span>
                    <span id="prevDias" class="font-bold text-gray-800 bg-white px-3 py-0.5 rounded-full border border-blue-200">—</span>
                </div>
                <div id="saldoWarning" class="hidden mt-2 p-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i><span id="saldoWarningText"></span>
                </div>
            </div>

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
                <p id="saldoIndicador" class="hidden text-xs text-amber-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>Este permiso descuenta saldo vacacional.
                </p>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                <textarea id="observaciones" rows="2"
                          class="w-full border border-gray-200 rounded-lg p-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition resize-none"
                          placeholder="Opcional..."></textarea>
            </div>

            <div id="formMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>

            <button id="btnSolicitar" onclick="solicitarFechas()"
                    class="w-full bg-primary hover:bg-blue-900 text-white font-semibold py-3 px-4 rounded-xl shadow-sm transition flex justify-center items-center gap-2">
                <i class="ph ph-paper-plane-right text-lg"></i> Enviar Solicitud
            </button>
        </div>
    </div>

    {{-- ── DERECHA: Solicitudes del equipo + las mías ── --}}
    <div class="lg:col-span-8 space-y-5">

        {{-- Solicitudes del equipo --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-blue-50/30 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-primary flex items-center gap-2">
                        <i class="ph ph-users-three text-xl"></i> Solicitudes de mi Equipo
                    </h2>
                    <p class="text-xs text-gray-500">Otorga el Visto Bueno o rechaza antes de enviar a RH.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span id="badge-pendientes"
                          class="hidden bg-yellow-100 text-yellow-800 text-xs font-bold px-2.5 py-1 rounded-full border border-yellow-200"></span>
                    <div class="flex items-center gap-1.5">
                        <button onclick="toggleRechazadasEquipo()"
                                id="btnToggleRechazadas"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                                title="Mostrar/ocultar rechazadas">
                            <i class="ph ph-eye-slash" id="iconToggleRech"></i>
                            <span id="lblToggleRech">Ocultar rechazadas</span>
                        </button>
                        <button onclick="verGrupo()"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-indigo-600 border border-indigo-200 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                            <i class="ph ph-users text-sm"></i>
                            Mi Equipo
                        </button>
                        <button onclick="cargarEquipo()"
                                class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                            <i class="fas fa-sync-alt text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3">Empleado</th>
                            <th class="px-5 py-3">Fechas</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tablaEquipo" class="divide-y divide-gray-100">
                        <tr><td colspan="5" class="py-8 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin text-primary text-lg mb-1 block"></i>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mis solicitudes --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Mis Solicitudes</h2>
                    <div class="h-1 w-12 bg-amber-500 mt-1 rounded-full"></div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button onclick="toggleCanceladasSup()"
                            id="btnToggleSupCanceladas"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        <i class="ph ph-eye-slash" id="iconToggleSup"></i>
                        <span id="lblToggleSup">Ocultar canceladas</span>
                    </button>
                    <button onclick="cargarMisSolicitudes()"
                            class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                        <i class="fas fa-sync-alt text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white border-b border-gray-100 text-xs uppercase text-gray-400 font-bold tracking-wider">
                        <tr>
                            <th class="px-5 py-3">Fechas</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-center">Regreso</th>
                            <th class="px-5 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMisSolicitudes" class="divide-y divide-gray-100">
                        <tr><td colspan="5" class="py-6 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-primary mr-1"></i>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL VER EQUIPO ── --}}
<div id="equipoModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('equipoModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 bg-indigo-600 text-white flex justify-between items-center">
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="ph ph-users-three text-xl text-indigo-200"></i>
                    Miembros de mi Equipo
                </h3>
                <button onclick="closeModal('equipoModal')" class="text-indigo-200 hover:text-white">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div id="equipoModalContenido" class="px-6 py-5 max-h-[480px] overflow-y-auto">
                <div class="text-center text-gray-400 py-6">
                    <i class="fas fa-spinner fa-spin text-indigo-400 text-xl mb-2 block"></i>Cargando...
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 flex justify-end">
                <button onclick="closeModal('equipoModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL EVALUAR SOLICITUD ── --}}
<div id="evaluarModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('evaluarModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-5 bg-primary text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i class="ph ph-clipboard-text text-xl text-amber-400"></i> Evaluar Solicitud
                </h3>
                <p class="text-xs text-blue-200 mt-1">Revisa y toma una decisión antes de enviar a RH</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Empleado</label>
                        <input type="text" id="evalNombre" disabled
                               class="w-full border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-sm text-gray-700">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fechas</label>
                        <input type="text" id="evalFechas" disabled
                               class="w-full border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-sm text-gray-700">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo</label>
                        <input type="text" id="evalTipo" disabled
                               class="w-full border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-sm text-gray-700">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Días hábiles</label>
                        <input type="text" id="evalDias" disabled
                               class="w-full border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-sm text-gray-700">
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <label class="block text-xs font-bold text-primary uppercase mb-2">Decisión del Supervisor</label>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button type="button" onclick="seleccionarDecision(2)"
                                id="btnVoBo"
                                class="decision-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-gray-200 text-sm font-semibold text-gray-600 hover:border-green-400 hover:bg-green-50 hover:text-green-700 transition">
                            <i class="fas fa-check-circle text-green-500"></i>
                            Visto Bueno → RH
                        </button>
                        <button type="button" onclick="seleccionarDecision(3)"
                                id="btnRechazar"
                                class="decision-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-gray-200 text-sm font-semibold text-gray-600 hover:border-red-400 hover:bg-red-50 hover:text-red-700 transition">
                            <i class="fas fa-times-circle text-red-400"></i>
                            Rechazar
                        </button>
                    </div>
                    <input type="hidden" id="evalDecision" value="">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">
                        Observaciones / Motivo
                        <span id="motivoReq" class="hidden text-red-500 ml-1">*requerido al rechazar</span>
                    </label>
                    <textarea id="evalObservacion" rows="2"
                              class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none resize-none"
                              placeholder="Ej: Se cubre con el compañero X / Falta de personal..."></textarea>
                </div>

                <div id="evalMsg" class="hidden p-3 rounded-lg text-sm"></div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button onclick="closeModal('evaluarModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100">Cancelar</button>
                <button onclick="confirmarEvaluacion()"
                        class="px-5 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold shadow-sm transition">
                    Confirmar y Enviar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL CANCELAR MIS SOLICITUDES ── --}}
<div id="deleteModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('deleteModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-warning text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">¿Cancelar solicitud?</h3>
            <p class="text-sm text-gray-500 mb-5">El saldo será devuelto si aplica.</p>
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
    <script>
        window.saldoActual = {{ $emp->saldo }};
    </script>
    @vite([
        'resources/js/tiposSolicitud.js', 
        'resources/js/sup_user.js'
    ])
@endpush