@extends('layouts.app')
@section('title', 'Personal — Canel\'s')

@section('content')
@php $emp = Auth::guard('empleado')->user(); @endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Directorio de Personal</h1>
        <div class="h-1 w-16 bg-amber-500 mt-1 rounded-full"></div>
        <p class="text-sm text-gray-500 mt-1">Gestiona roles, contraseñas y estado de los empleados.</p>
    </div>
    @if($emp->rol == 4)
    <a href="{{ route('maintenance') }}"
       class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold py-2 px-4 rounded-xl shadow-sm transition flex items-center gap-2 self-start">
        <i class="fas fa-tools"></i> Mantenimiento
    </a>
    @endif
</div>

{{-- ── Pestañas ── --}}
<div class="flex gap-1 mb-4 bg-white rounded-xl p-1.5 shadow-sm border border-gray-100 w-fit">
    <button onclick="cambiarPestana('personal')" id="tab-personal"
            class="tab-btn px-4 py-2 rounded-lg text-sm font-semibold transition bg-primary text-white">
        <!-- <i class="ph ph-users mr-1.5"></i> Directorio -->Directorio
    </button>
    @if($emp->rol == 4)
    <button onclick="cambiarPestana('seguridad')" id="tab-seguridad"
            class="tab-btn px-4 py-2 rounded-lg text-sm font-semibold transition text-gray-600 hover:bg-gray-100">
        <!-- <i class="fas fa-shield-alt mr-1.5"></i>  --> Seguridad
    </button>
    @endif
</div>

{{-- ── PANEL DIRECTORIO ── --}}
<div id="panel-personal">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    {{-- Filtros --}}
    <div class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-center">

        {{-- Buscador --}}
        <div class="relative flex-1 min-w-[200px]">
            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
            <input type="text" id="buscarPersonal"
                   class="w-full pl-8 pr-20 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                   placeholder="Nómina o nombre..."
                   onkeydown="if(event.key==='Enter') cargarPersonal(1)">
            <button onclick="cargarPersonal(1)"
                    class="absolute inset-y-0 right-0 px-3 text-xs font-bold text-white bg-primary hover:bg-blue-900 rounded-r-lg transition">
                Buscar
            </button>
        </div>

        {{-- Filtro Estado (Activo/Inactivo) --}}
        <div class="flex items-center gap-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">Estado:</label>
            <div class="flex gap-1">
                <button onclick="filtrarActivo('1')" data-activo="1"
                        class="filtro-activo px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-primary text-white border-primary">
                    Activos
                </button>
                <button onclick="filtrarActivo('0')" data-activo="0"
                        class="filtro-activo px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    Inactivos
                </button>
                <button onclick="filtrarActivo('todos')" data-activo="todos"
                        class="filtro-activo px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    Todos
                </button>
            </div>
        </div>

        {{-- Filtro Rol --}}
        <div class="flex items-center gap-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">Rol:</label>
            <div class="flex gap-1 flex-wrap">
                <button onclick="filtrarRol('')" data-rol=""
                        class="filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-gray-100 text-gray-700 border-gray-200">
                    Todos
                </button>
                <button onclick="filtrarRol(1)" data-rol="1"
                        class="filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    Empleado
                </button>
                <button onclick="filtrarRol(2)" data-rol="2"
                        class="filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    Supervisor
                </button>
                <button onclick="filtrarRol(3)" data-rol="3"
                        class="filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    Admin RH
                </button>
                @if($emp->rol == 4)
                <button onclick="filtrarRol(4)" data-rol="4"
                        class="filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition">
                    SuperAdmin
                </button>
                @endif
            </div>
        </div>

        <span id="totalBadge" class="text-xs text-gray-400 ml-auto"></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 cursor-pointer hover:bg-gray-100 transition select-none"
                        data-sort="nombre" onclick="ordenar('nombre')">
                        Empleado <i class="fas fa-sort text-gray-300 ml-1 text-[10px]"></i>
                    </th>
                    <th class="px-5 py-3 text-center cursor-pointer hover:bg-gray-100 transition select-none"
                        data-sort="saldo" onclick="ordenar('saldo')">
                        Saldo <i class="fas fa-sort text-gray-300 ml-1 text-[10px]"></i>
                    </th>
                    <th class="px-5 py-3">Centro</th>
                    <th class="px-5 py-3">Rol</th>
                    <th class="px-5 py-3 text-center">Estado</th>
                    <th class="px-5 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaPersonal" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="py-10 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin text-primary text-lg mb-1 block"></i>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 border-t border-gray-100 flex justify-between items-center">
        <p id="personalInfo" class="text-xs text-gray-500"></p>
        <div id="personalPagina" class="flex gap-1"></div>
    </div>
</div>

</div>{{-- /panel-personal --}}

{{-- ── PANEL SEGURIDAD ── --}}
@if($emp->rol == 4)
<div id="panel-seguridad" class="hidden space-y-5">

    {{-- IPs Bloqueadas --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    IPs Bloqueadas
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">Direcciones IP con bloqueo permanente por intentos fallidos</p>
            </div>
            <button onclick="cargarIpsBloqueadas()"
                    class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                <i class="fas fa-sync-alt text-xs"></i>
            </button>
        </div>
        <div id="tablaIPs" class="divide-y divide-gray-100 min-h-[80px]">
            <div class="py-6 text-center text-gray-400 text-sm">
                <i class="fas fa-spinner fa-spin mr-2"></i>Cargando...
            </div>
        </div>
    </div>

    {{-- Usuarios bloqueados --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    Usuarios Bloqueados
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">Cuentas con bloqueo permanente por exceso de intentos fallidos</p>
            </div>
            <button onclick="cargarUsuariosBloqueados()"
                    class="p-1.5 text-gray-400 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Actualizar">
                <i class="fas fa-sync-alt text-xs"></i>
            </button>
        </div>
        <div id="tablaUsuariosBloqueados" class="divide-y divide-gray-100 min-h-[80px]">
            <div class="py-6 text-center text-gray-400 text-sm">
                <i class="fas fa-spinner fa-spin mr-2"></i>Cargando...
            </div>
        </div>
    </div>

</div>
@endif

{{-- Modal: Cambiar Rol --}}
<div id="editRolModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('editRolModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Cambiar Rol</h3>
            <p class="text-sm text-gray-500 mb-4">Empleado: <span id="editRolNombre" class="font-semibold text-gray-800"></span></p>
            <select id="editRolValor" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none mb-4">
                <option value="1">Empleado</option>
                <option value="2">Supervisor</option>
                <option value="3">Admin RH</option>
                @if($emp->rol == 4)<option value="4">SuperAdmin</option>@endif
            </select>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-700">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Supervisor</strong> puede liderar grupos. <strong>Admin RH</strong> accede al panel de vacaciones.
            </div>
            <div id="editRolMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3">
                <button onclick="closeModal('editRolModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="guardarRol()" class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold transition">Guardar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Restablecer Contraseña --}}
<div id="resetPwdModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('resetPwdModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Restablecer Contraseña</h3>
            <p class="text-sm text-gray-500 mb-4">
                <span id="resetPwdNombreDisplay" class="font-semibold text-gray-800"></span>
                &nbsp;·&nbsp;<span id="resetPwdNomina" class="font-mono text-gray-500"></span>
            </p>
            <div class="space-y-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nueva contraseña</label>
                    <div class="relative">
                        <input type="password" id="resetPwdNueva" placeholder="Mínimo 8 caracteres"
                               class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <button type="button" onclick="togglePwd('resetPwdNueva', this)"
                                class="absolute inset-y-0 right-0 px-3 flex items-center hover:bg-gray-50 rounded-r-lg transition">
                            <i class="fas fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Confirmar contraseña</label>
                    <div class="relative">
                        <input type="password" id="resetPwdConfirmar"
                               class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <button type="button" onclick="togglePwd('resetPwdConfirmar', this)"
                                class="absolute inset-y-0 right-0 px-3 flex items-center hover:bg-gray-50 rounded-r-lg transition">
                            <i class="fas fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div id="resetPwdMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3">
                <button onclick="closeModal('resetPwdModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button onclick="guardarPassword()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition">Guardar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Dar de Baja --}}
<div id="bajaModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40" onclick="closeModal('bajaModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-user-minus text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-1">¿Dar de baja?</h3>
            <p class="text-sm text-gray-500 mb-1"><strong id="bajaNombreDisplay" class="text-gray-800"></strong> perderá acceso al sistema.</p>
            <p class="text-xs text-gray-400 mb-5">Nómina: <span id="bajaNominaDisplay" class="font-mono"></span></p>
            <div id="bajaMsg" class="hidden mb-3 p-3 rounded-lg text-sm text-left"></div>
            <div class="flex flex-col gap-2">
                <button id="btnConfirmarBaja" onclick="confirmarBaja()"
                        class="w-full px-4 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition">
                    Sí, dar de baja
                </button>
                <button onclick="closeModal('bajaModal')" class="w-full px-4 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">Cancelar</button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL ELIMINAR EMPLEADO (HARD) — solo SuperAdmin ── --}}
@if($emp->rol == 4)
<div id="hardDeleteEmpModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
         onclick="closeModal('hardDeleteEmpModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-red-700 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-1">Eliminar permanentemente</h3>
            <p class="text-sm text-gray-500 mb-1">
                <strong id="hardDeleteEmpNombre" class="text-gray-800"></strong>
            </p>
            <p class="text-xs text-gray-400 mb-3">
                Nómina: <span id="hardDeleteEmpNomina" class="font-mono"></span>
            </p>

            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-left">
                <p class="text-xs text-red-700 font-semibold mb-1">
                    <i class="fas fa-exclamation-triangle mr-1"></i>Esta acción es irreversible
                </p>
                <ul class="text-xs text-red-600 space-y-0.5 list-disc list-inside">
                    <li>Se borrará el registro del empleado</li>
                    <li>Se borrarán sus solicitudes y historial</li>
                    <li>Se eliminarán sus membresías de grupos</li>
                    <li>No se puede deshacer</li>
                </ul>
            </div>

            <div id="hardDeleteEmpMsg" class="hidden mb-3 p-3 rounded-lg text-sm text-left"></div>

            <div class="flex flex-col gap-2">
                <button id="btnConfirmarHardDeleteEmp" onclick="confirmarEliminarEmpleado()"
                        class="w-full px-4 py-2.5 bg-red-700 text-white font-bold rounded-xl
                               hover:bg-red-800 transition flex items-center justify-center gap-2">
                    <i class="ph ph-trash"></i> Sí, eliminar permanentemente
                </button>
                <button onclick="closeModal('hardDeleteEmpModal')"
                        class="w-full px-4 py-2.5 bg-gray-100 text-gray-700 font-bold
                               rounded-xl hover:bg-gray-200 transition">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
    <script>
        window.MI_ROL = @js((int) $emp->rol);
        window.MI_NOMINA = @js($emp->nomina);
    </script>
    @vite(['resources/js/personal.js'])
@endpush