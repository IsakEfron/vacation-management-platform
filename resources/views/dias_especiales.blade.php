@extends('layouts.app')
@section('title', 'Configuración — Canel\'s')

@section('content')
@php $emp = Auth::guard('empleado')->user(); @endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Configuración del Sistema</h1>
        <div class="h-1 w-16 bg-amber-500 mt-1 rounded-full"></div>
        <p class="text-sm text-gray-500 mt-1">Días hábiles, quincenas y tipos de solicitud.</p>
    </div>
</div>

{{-- ── Pestañas ── --}}
<div class="flex gap-1 mb-5 bg-white rounded-xl p-1.5 shadow-sm border border-gray-100 w-fit">
    <button onclick="cambiarTab('dias')" id="tab-dias"
            class="config-tab px-4 py-2 rounded-lg text-sm font-semibold transition bg-primary text-white">
        <i class="fas fa-calendar-times mr-1.5"></i>Días Especiales
    </button>
    <button onclick="cambiarTab('quincenas')" id="tab-quincenas"
            class="config-tab px-4 py-2 rounded-lg text-sm font-semibold transition text-gray-600 hover:bg-gray-100">
        <i class="fas fa-calendar-alt mr-1.5"></i>Quincenas
    </button>
    <button onclick="cambiarTab('tipos')" id="tab-tipos"
            class="config-tab px-4 py-2 rounded-lg text-sm font-semibold transition text-gray-600 hover:bg-gray-100">
        <i class="fas fa-tags mr-1.5"></i>Tipos de Solicitud
    </button>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PANEL: DÍAS ESPECIALES                                        --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="panel-dias">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Columna izquierda --}}
    <div class="lg:col-span-7 space-y-5">

        {{-- Agregar día --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-primary"></i> Registrar Día Especial
            </h2>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha</label>
                    <input type="date" id="diaFecha"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo</label>
                    <select id="diaTipo"
                            class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="feriado">Feriado oficial</option>
                        <option value="puente">Día puente</option>
                        <option value="especial">Día especial</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción</label>
                    <input type="text" id="diaDescripcion" placeholder="Ej: Día de la Constitución"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Aplica a</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="aplicaA" value="todos" checked class="text-primary"> Todos los centros
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="aplicaA" value="especifico" id="radioEspecifico" class="text-primary"> Centro específico
                        </label>
                    </div>
                    <select id="diaAplicaCentro"
                            class="hidden w-full mt-2 border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="">Cargando centros...</option>
                    </select>
                    <input type="hidden" id="diaAplicaA" value="todos">
                </div>
            </div>
            <div id="diaMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <button onclick="agregarDia()"
                    class="bg-primary hover:bg-blue-900 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Agregar
            </button>
        </div>

        {{-- Lista de días --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-times text-red-400"></i> Días No Laborables
                </h2>
                <div class="flex items-center gap-2">
                    <select id="filtroAnio" onchange="cargarDias()"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-primary">
                        @for($y = now()->year - 1; $y <= now()->year + 2; $y++)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button id="btnFiltroActivo" onclick="toggleFiltroActivo()"
                            class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-primary hover:text-primary transition">
                        <i class="fas fa-eye-slash mr-1"></i> Ver desactivados
                    </button>
                    <button onclick="cargarDias()" class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition">
                        <i class="fas fa-sync-alt text-xs"></i>
                    </button>
                </div>
            </div>
            <div id="listaDias" class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                <div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>
            </div>
        </div>
    </div>

    {{-- Columna derecha —días hábiles por centro --}}
    <div class="lg:col-span-5 space-y-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                <i class="fas fa-building text-indigo-500"></i> Días Hábiles por Centro
            </h2>
            <p class="text-xs text-gray-500 mb-4">Regla global: <strong>Lunes a Sábado</strong>.</p>
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Centro de trabajo</label>
                <select id="centroPago" onchange="cargarConfigCentro(this.value)"
                        class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <option value="">Seleccionar centro...</option>
                </select>
            </div>
            <div id="panelDiasSemana" class="hidden">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">
                    Días hábiles <span class="normal-case font-normal text-gray-400">(clic para activar/desactivar)</span>
                </label>
                <div class="grid grid-cols-7 gap-1.5 mb-4">
                    @php
                    $diasLetras  = ['L','M','X','J','V','S','D'];
                    $diasNombres = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                    $defHabil    = [true, true, true, true, true, true, false];
                    @endphp
                    @foreach($diasLetras as $i => $letra)
                    <div class="text-center">
                        <button type="button" data-dia="{{ $i + 1 }}" data-activo="{{ $defHabil[$i] ? '1' : '0' }}"
                                onclick="toggleDia(this)"
                                class="dia-btn w-full h-10 flex items-center justify-center rounded-lg text-xs font-bold border-2 transition-all select-none
                                       {{ $defHabil[$i] ? 'bg-primary text-white border-primary shadow-sm' : 'bg-gray-100 text-gray-400 border-gray-200' }}">
                            {{ $letra }}
                        </button>
                        <p class="text-[9px] text-gray-400 mt-0.5 leading-tight">{{ $diasNombres[$i] }}</p>
                    </div>
                    @endforeach
                </div>
                <div id="centroMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
                <div class="flex gap-2">
                    <button onclick="guardarCentro()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                    <button onclick="eliminarCentro()" class="px-4 bg-gray-100 hover:bg-red-50 text-gray-600 hover:text-red-600 py-2.5 rounded-xl text-sm font-semibold transition" title="Eliminar configuración">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <div id="sinCentro" class="text-center py-6 text-gray-400 text-sm">
                <i class="fas fa-mouse-pointer text-2xl mb-2 block text-gray-300"></i>
                Selecciona un centro para configurar sus días
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i class="fas fa-list-check text-green-500"></i> Centros Configurados
            </h3>
            <div id="centrosConfigurados" class="text-xs text-gray-400">Cargando...</div>
        </div>
    </div>
</div>
</div>{{-- /panel-dias --}}

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PANEL: QUINCENAS                                              --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="panel-quincenas" class="hidden">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Formulario agregar quincena --}}
    <div class="lg:col-span-5 space-y-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-emerald-600"></i> Registrar Quincena
            </h2>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Año</label>
                    <input type="number" id="qAnio" value="{{ now()->year }}" min="2020" max="2099"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Número (1-24)</label>
                    <input type="number" id="qNumero" min="1" max="24" placeholder="Ej: 1"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción</label>
                    <input type="text" id="qDescripcion" placeholder="Ej: Q1 — 1a. quincena Enero 2025"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha inicio</label>
                    <input type="date" id="qFechaInicio"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha fin</label>
                    <input type="date" id="qFechaFin"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div id="qMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <button onclick="agregarQuincena()"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition flex items-center justify-center gap-2">
                <i class="fas fa-plus"></i> Registrar Quincena
            </button>
        </div>

        {{-- Generar año completo --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fas fa-magic text-purple-500"></i> Generar Año Automáticamente
            </h3>
            <p class="text-xs text-gray-500 mb-3">Crea las 24 quincenas del año usando la regla estándar (1-15 y 16-fin de mes). Puedes editarlas después para ajustar las fechas reales.</p>
            <div class="flex gap-2">
                <input type="number" id="qGenerarAnio" value="{{ now()->year }}" min="2020" max="2099"
                       class="flex-1 border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                <button onclick="generarQuincenas()"
                        class="px-4 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-1.5">
                    <i class="fas fa-wand-magic-sparkles"></i> Generar
                </button>
            </div>
            <div id="qGenerarMsg" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
        </div>
    </div>

    {{-- Lista de quincenas --}}
    <div class="lg:col-span-7">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-emerald-500"></i> Quincenas Registradas
                </h2>
                <div class="flex items-center gap-2">
                    <select id="qFiltroAnio" onchange="cargarQuincenas()"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-primary">
                        @for($y = now()->year - 1; $y <= now()->year + 2; $y++)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button id="btnQFiltroActivo" onclick="toggleQFiltroActivo()"
                            class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-primary hover:text-primary transition">
                        <i class="fas fa-eye-slash mr-1"></i> Ver inactivas
                    </button>
                    <button onclick="cargarQuincenas()" class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition">
                        <i class="fas fa-sync-alt text-xs"></i>
                    </button>
                </div>
            </div>
            <div id="listaQuincenas" class="divide-y divide-gray-100 max-h-[560px] overflow-y-auto">
                <div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>
            </div>
        </div>
    </div>
</div>
</div>{{-- /panel-quincenas --}}

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PANEL: TIPOS DE SOLICITUD                                     --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="panel-tipos" class="hidden">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Formulario agregar tipo --}}
    <div class="lg:col-span-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-tag text-orange-500"></i> Registrar Tipo de Solicitud
            </h2>
            <div class="space-y-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nombre</label>
                    <input type="text" id="tipoNombre" placeholder="Ej: Permiso médico"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-200">
                        <p class="text-xs font-bold text-gray-700 mb-2">Con goce de sueldo</p>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" name="tipoConGoce" value="1" checked class="text-primary"> Sí
                            </label>
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" name="tipoConGoce" value="0" class="text-primary"> No
                            </label>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-200">
                        <p class="text-xs font-bold text-gray-700 mb-2">Descuenta saldo</p>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" name="tipoUsaSaldo" value="1" class="text-primary"> Sí
                            </label>
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" name="tipoUsaSaldo" value="0" checked class="text-primary"> No
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-700">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Descuenta saldo</strong>: Los días solicitados se restan del saldo vacacional del empleado.
            </div>
            <div id="tipoMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <button onclick="agregarTipo()"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition flex items-center justify-center gap-2">
                <i class="fas fa-plus"></i> Registrar Tipo
            </button>
        </div>
    </div>

    {{-- Lista de tipos --}}
    <div class="lg:col-span-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-tags text-orange-400"></i> Tipos de Solicitud
                </h2>
                <button onclick="cargarTipos()" class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition">
                    <i class="fas fa-sync-alt text-xs"></i>
                </button>
            </div>
            <div id="listaTipos" class="divide-y divide-gray-100">
                <div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>
            </div>
        </div>
    </div>
</div>
</div>{{-- /panel-tipos --}}

{{-- ═══════════════════════════════════════ MODALES ═══════════════════════════════════════ --}}

{{-- Modal: Editar Día Especial --}}
<div id="modalEditarDia" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                <i class="fas fa-pencil-alt text-primary"></i> Editar Día Especial
            </h3>
            <button onclick="cerrarModalEditarDia()" class="text-gray-400 hover:text-gray-600 transition text-xl leading-none">×</button>
        </div>
        <input type="hidden" id="editDiaId">
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha</label>
                <input type="date" id="editDiaFecha" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo</label>
                <select id="editDiaTipo" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <option value="feriado">Feriado oficial</option>
                    <option value="puente">Día puente</option>
                    <option value="especial">Día especial</option>
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción</label>
                <input type="text" id="editDiaDescripcion" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Aplica a</label>
                <select id="editDiaAplicaA" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <option value="todos">Todos los centros</option>
                </select>
            </div>
        </div>
        <div id="editDiaMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
        <div class="flex gap-2">
            <button onclick="guardarEdicionDia()" class="flex-1 bg-primary hover:bg-blue-900 text-white py-2.5 rounded-xl text-sm font-semibold transition">
                <i class="fas fa-save mr-1"></i> Guardar cambios
            </button>
            <button onclick="cerrarModalEditarDia()" class="px-5 bg-gray-100 hover:bg-gray-200 text-gray-600 py-2.5 rounded-xl text-sm font-semibold transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

{{-- Modal: Editar Quincena --}}
<div id="modalEditarQuincena" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                <i class="fas fa-pencil-alt text-emerald-600"></i> Editar Quincena
            </h3>
            <button onclick="cerrarModalEditarQ()" class="text-gray-400 hover:text-gray-600 transition text-xl leading-none">×</button>
        </div>
        <input type="hidden" id="editQId">
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Año</label>
                <input type="number" id="editQAnio" min="2020" max="2099" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Número (1-24)</label>
                <input type="number" id="editQNumero" min="1" max="24" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción</label>
                <input type="text" id="editQDescripcion" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha inicio</label>
                <input type="date" id="editQFechaInicio" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha fin</label>
                <input type="date" id="editQFechaFin" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
        </div>
        <div id="editQMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
        <div class="flex gap-2">
            <button onclick="guardarEdicionQ()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">
                <i class="fas fa-save mr-1"></i> Guardar cambios
            </button>
            <button onclick="cerrarModalEditarQ()" class="px-5 bg-gray-100 hover:bg-gray-200 text-gray-600 py-2.5 rounded-xl text-sm font-semibold transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

{{-- Modal: Editar Tipo de Solicitud --}}
<div id="modalEditarTipo" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40" onclick="cerrarModalEditarTipo()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                <i class="fas fa-pencil-alt text-orange-500"></i> Editar Tipo de Solicitud
            </h3>
            <button onclick="cerrarModalEditarTipo()" class="text-gray-400 hover:text-gray-600 transition text-xl leading-none">×</button>
        </div>
        <input type="hidden" id="editTipoId">
        <div class="space-y-3 mb-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nombre</label>
                <input type="text" id="editTipoNombre" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-xl p-3 border border-gray-200">
                    <p class="text-xs font-bold text-gray-700 mb-2">Con goce de sueldo</p>
                    <div class="flex gap-2">
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="editTipoConGoce" value="1" class="text-primary"> Sí
                        </label>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="editTipoConGoce" value="0" class="text-primary"> No
                        </label>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-3 border border-gray-200">
                    <p class="text-xs font-bold text-gray-700 mb-2">Descuenta saldo</p>
                    <div class="flex gap-2">
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="editTipoUsaSaldo" value="1" class="text-primary"> Sí
                        </label>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="editTipoUsaSaldo" value="0" class="text-primary"> No
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div id="editTipoMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
        <div class="flex gap-2">
            <button onclick="guardarEdicionTipo()" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-xl text-sm font-semibold transition">
                <i class="fas fa-save mr-1"></i> Guardar cambios
            </button>
            <button onclick="cerrarModalEditarTipo()" class="px-5 bg-gray-100 hover:bg-gray-200 text-gray-600 py-2.5 rounded-xl text-sm font-semibold transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/dias_especiales.js'])
@endpush