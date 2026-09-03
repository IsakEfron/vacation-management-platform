@extends('layouts.app')
@section('title', 'Gestión de Grupos — Canel\'s')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Equipos</h1>
        <div class="h-1 w-16 bg-amber-500 mt-1 rounded-full"></div>
        <p class="text-sm text-gray-500 mt-2">Administra grupos de trabajo y sus supervisores.</p>
    </div>
    <button onclick="openModal('nuevoGrupoModal')"
            class="flex items-center gap-2 bg-primary hover:bg-blue-900 text-white px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
        <i class="ph ph-plus text-lg"></i> Nuevo Grupo
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Lista de grupos --}}
    <div class="lg:col-span-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph ph-users-three text-primary"></i> Grupos Activos
                </h2>
                <span id="totalGrupos" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-bold">0</span>
            </div>
            <ul id="listaGrupos" class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                <li class="p-4 text-center text-gray-400 text-sm">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Cargando...
                </li>
            </ul>
        </div>
    </div>

    {{-- Detalle del grupo --}}
    <div class="lg:col-span-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

            <div id="estadoVacio" class="flex flex-col items-center justify-center py-20 text-gray-400">
                <i class="ph ph-users-three text-5xl mb-3 text-gray-300"></i>
                <p class="text-sm font-medium">Selecciona un grupo para ver sus detalles</p>
            </div>

            <div id="contenidoGrupo" class="hidden">

                <div class="px-6 py-5 border-b border-gray-200 flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                    <div>
                        <h2 id="grupoNombre" class="text-xl font-bold text-gray-900"></h2>
                        <p class="text-sm text-gray-500">Gestión de miembros y supervisor.</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Añadir individual --}}
                        <button onclick="abrirAgregarMiembro()"
                                class="flex items-center gap-1.5 bg-primary hover:bg-blue-900 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="ph ph-user-plus"></i> Añadir
                        </button>
                        {{-- Importar JSON --}}
                        <button onclick="openModal('importarModal')"
                                class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="ph ph-file-arrow-up"></i> Importar JSON
                        </button>
                        {{-- Eliminar grupo --}}
                        <button onclick="eliminarGrupoActual()"
                                class="bg-red-50 text-red-600 hover:bg-red-100 p-2 rounded-lg transition" title="Eliminar grupo">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Supervisor --}}
                <div class="px-6 py-4 bg-blue-50/40 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="h-11 w-11 rounded-full bg-amber-500 flex items-center justify-center text-white shadow-md">
                            <i class="ph ph-crown text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-primary uppercase tracking-wider mb-0.5">Supervisor Asignado</p>
                            <p id="supervisorNombre" class="text-sm font-bold text-gray-900"></p>
                            <p id="supervisorNomina" class="text-xs text-gray-500"></p>
                        </div>
                    </div>
                    <button onclick="openModal('supervisorModal')"
                            class="text-sm text-blue-600 hover:text-blue-800 font-medium underline">Cambiar</button>
                </div>

                {{-- Miembros --}}
                <div class="p-6">
                    <h3 id="tituloMiembros" class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4"></h3>
                    <div id="gridMiembros" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL: Nuevo Grupo ── --}}
<div id="nuevoGrupoModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('nuevoGrupoModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Crear Nuevo Grupo</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nombre del grupo</label>
                    <input type="text" id="nuevoGrupoNombre" placeholder="Ej: Turno Matutino - Planta 2"
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Supervisor</label>
                    <input type="text" id="nuevoGrupoSupBuscar" placeholder="Escribe nómina o nombre (mín. 1 caracter)..."
                           class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"
                           oninput="buscarEmpleadoParaSup(this.value)">
                    <div id="dropdownSup" class="hidden border border-gray-200 rounded-lg mt-1 max-h-44 overflow-y-auto shadow-sm bg-white z-10 relative"></div>
                    <input type="hidden" id="nuevoGrupoSup">
                    <p id="supSeleccionado" class="hidden text-xs text-green-600 mt-1 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i><span id="supSeleccionadoNombre"></span>
                    </p>
                </div>
            </div>
            <div id="nuevoGrupoMsg" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 mt-5">
                <button onclick="closeModal('nuevoGrupoModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="crearGrupo()" class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold">Crear</button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL: Agregar miembro individual ── --}}
<div id="agregarModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('agregarModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Agregar Empleado al Grupo</h3>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Buscar empleado (nómina o nombre)</label>
                <input type="text" id="agregarBuscar" placeholder="Escribe al menos 1 caracter..."
                       class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"
                       oninput="buscarParaAgregar(this.value)">
                <div id="dropdownAgregar" class="hidden border border-gray-200 rounded-lg mt-1 max-h-44 overflow-y-auto shadow-sm bg-white relative z-10"></div>
                <input type="hidden" id="agregarNomina">
                <p id="agregarSeleccionado" class="hidden text-xs text-green-600 mt-1 flex items-center gap-1">
                    <i class="fas fa-check-circle"></i><span id="agregarSeleccionadoNombre"></span>
                </p>
            </div>
            <div id="agregarMsg" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 mt-5">
                <button onclick="closeModal('agregarModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="confirmarAgregarMiembro()" class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold">Agregar</button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL: Importar JSON masivo ── --}}
<div id="importarModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('importarModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-10 w-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="ph ph-file-arrow-up text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Importar Personal — JSON Masivo</h3>
                    <p class="text-xs text-gray-500">Agrega múltiples empleados al grupo en un solo paso</p>
                </div>
            </div>

            {{-- Ejemplo de formato --}}
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
                <p class="text-xs font-bold text-gray-600 uppercase mb-2">Formato aceptado:</p>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <p class="text-gray-500 mb-1">Opción A — Array de objetos:</p>
                        <pre class="bg-gray-800 text-green-300 rounded-lg p-2 text-[11px] overflow-x-auto">[
    {"nomina": "123456"},
    {"nomina": "789012"}
]</pre>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-1">Opción B — Array simple:</p>
                        <pre class="bg-gray-800 text-green-300 rounded-lg p-2 text-[11px] overflow-x-auto">[
    "123456",
    "789012",
    "345678"
]</pre>
                    </div>
                </div>
            </div>

            {{-- Área de texto o carga de archivo --}}
            <div class="mb-3">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-gray-500 uppercase">JSON de nóminas</label>
                    <label class="cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                        <i class="fas fa-file-upload text-xs"></i> Cargar archivo .json
                        <input type="file" accept=".json,application/json" class="hidden" id="jsonFileInput" onchange="cargarArchivoJSON(this)">
                    </label>
                </div>
                <textarea id="importarJSON" rows="6"
                          class="w-full border border-gray-300 rounded-lg p-3 text-sm font-mono text-gray-700 focus:ring-2 focus:ring-indigo-500 outline-none resize-none"
                          placeholder='["123456", "789012", "345678"]'></textarea>
            </div>

            {{-- Resultado de importación --}}
            <div id="importarResultado" class="hidden mb-3 p-4 rounded-xl border text-sm"></div>

            <div class="flex justify-end gap-3">
                <button onclick="closeModal('importarModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="ejecutarImportacion()"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class="ph ph-upload-simple"></i> Importar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL: Cambiar supervisor ── --}}
<div id="supervisorModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('supervisorModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Cambiar Supervisor</h3>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nuevo supervisor</label>
                <input type="text" id="supBuscar" placeholder="Escribe nómina o nombre..."
                       class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"
                       oninput="buscarParaSupervisor(this.value)">
                <div id="dropdownSupCambio" class="hidden border border-gray-200 rounded-lg mt-1 max-h-44 overflow-y-auto shadow-sm bg-white relative z-10"></div>
                <input type="hidden" id="nuevoSupervisor">
                <p id="supCambioSeleccionado" class="hidden text-xs text-green-600 mt-1 flex items-center gap-1">
                    <i class="fas fa-check-circle"></i><span id="supCambioNombre"></span>
                </p>
            </div>
            <div id="supMsg" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 mt-5">
                <button onclick="closeModal('supervisorModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="confirmarCambioSupervisor()" class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold">Confirmar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite(['resources/js/grupos.js'])
@endpush