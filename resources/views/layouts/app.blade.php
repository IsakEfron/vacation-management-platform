<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', "Canel's")</title>
    <link rel="icon" type="image/png" href="{{ asset('img/canels-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://unpkg.com/@phosphor-icons/web" defer></script>
    <style>body { visibility: hidden; }</style>
    {{-- 1. Variables de PHP globales (DEBEN ir antes de cargar los scripts de Vite) --}}
    <script>
        window._APP = {
            passwordChangeRoute: '{{ route("password.change") }}',
            primeraVezForced:    {{ (session('primera_vez') || (Auth::guard('empleado')->check() && Auth::guard('empleado')->user()->primera_vez)) ? 'true' : 'false' }},
            esAdmin:             {{ Auth::guard('empleado')->check() && Auth::guard('empleado')->user()?->rol == 4 ? 'true' : 'false' }},
        };
    </script>

    {{-- 2. Carga unificada de Vite para CSS y el JS Global (layout.js y app.js si lo usas) --}}
    @vite([
        'resources/css/app.css', 
        'resources/js/layout.js'
    ])

    <script>
        // Esperar a que los estilos estén realmente aplicados
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                requestAnimationFrame(() => document.body.style.visibility = 'visible');
            });
        } else {
            requestAnimationFrame(() => document.body.style.visibility = 'visible');
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen font-sans"
      data-primera-vez="{{ Auth::guard('empleado')->check() && Auth::guard('empleado')->user()->primera_vez ? '1' : '0' }}"
      data-timeout-seg="{{ Auth::guard('empleado')->check() && Auth::guard('empleado')->user()?->rol == 4 ? 0 : 3600 }}"
      data-primera-vez-forced="{{ (session('primera_vez') || (Auth::guard('empleado')->check() && Auth::guard('empleado')->user()->primera_vez)) ? '1' : '0' }}">

    {{-- ── Header ── --}}
    <header class="bg-primary shadow-md w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center h-auto md:h-20 py-3 md:py-0">

                <a href="{{ route('users') }}"
                   class="flex-shrink-0 flex items-center bg-white w-[110px] p-2 rounded-xl hover:shadow-lg transition duration-300">
                    <img src="{{ asset('img/canels-Logo.png') }}" alt="Logo Canels" class="w-full">
                </a>

                <nav class="flex flex-wrap justify-center md:justify-end items-center gap-2 md:gap-3 w-full md:w-auto mt-3 md:mt-0">
                    @php $emp = Auth::guard('empleado')->user(); @endphp

                    @if($emp && $emp->rol >= 3)
                        <a href="{{ route('admin') }}"
                           class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition {{ request()->routeIs('admin') ? 'bg-white/20 text-white' : '' }}">
                            Vacaciones
                        </a>
                        <a href="{{ route('grupos') }}"
                           class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition {{ request()->routeIs('grupos') ? 'bg-white/20 text-white' : '' }}">
                            Grupos
                        </a>
                        <a href="{{ route('personal') }}"
                           class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition {{ request()->routeIs('personal') ? 'bg-white/20 text-white' : '' }}">
                            Personal
                        </a>
                        <a href="{{ route('dias_especiales') }}"
                           class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition {{ request()->routeIs('dias_especiales') ? 'bg-white/20 text-white' : '' }}">
                            Días Hábiles
                        </a>
                    @endif

                    @if($emp && $emp->rol == 4)
                        <a href="{{ route('maintenance') }}"
                           class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition {{ request()->routeIs('maintenance') ? 'bg-white/20 text-white' : '' }}">
                            Mantenimiento
                        </a>
                    @endif

                    @if($emp)
                    <div class="hidden md:flex items-center gap-1 bg-white/10 rounded-lg px-3 py-1.5 ml-1">
                        <i class="fas fa-user-circle text-white/70 text-sm"></i>
                        <span class="text-white/80 text-xs font-medium">{{ $emp->nombre }}</span>
                        <span class="text-white/40 text-xs">•</span>
                        <span class="text-amber-300 text-xs font-semibold">{{ $emp->rolInfo->tipo ?? '—' }}</span>
                    </div>

                    <div class="relative">
                        <button onclick="toggleNotif()"
                                class="relative text-white hover:bg-white/10 p-2 rounded-lg transition"
                                title="Mantenimientos programados">
                            <i class="fas fa-bell text-lg"></i>
                            <span id="notifBadge"
                                  class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black rounded-full h-4 w-4 flex items-center justify-center leading-none">
                                0
                            </span>
                        </button>
                    </div>
                    @endif

                    <button onclick="openModal('changePasswordModal')"
                            class="text-white/80 hover:text-white hover:bg-white/10 text-sm font-medium px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                        <i class="fas fa-key text-xs"></i>
                        <span class="hidden sm:inline">Contraseña</span>
                    </button>

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="bg-white/10 hover:bg-red-500 text-white text-xs font-bold py-2 px-3 rounded-lg flex items-center gap-1.5 transition">
                            <i class="fas fa-sign-out-alt text-xs"></i>
                            <span>Salir</span>
                        </button>
                    </form>
                </nav>
            </div>
        </div>
    </header>

    {{-- ── Panel de Notificaciones ── --}}
    @if(Auth::guard('empleado')->check())
    <div id="notifPanel"
         class="hidden fixed top-20 right-4 md:right-8 z-50 bg-white rounded-2xl shadow-2xl border border-gray-200 w-full md:w-[420px] max-w-[95vw] overflow-hidden"
         style="transition: opacity 0.25s ease, transform 0.25s ease; opacity:0; transform:translateY(-10px)">

        <div class="bg-gradient-to-r from-primary to-blue-600 text-white px-5 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="fas fa-calendar-alt"></i>
                <span class="font-bold text-sm">Mantenimientos Programados</span>
            </div>
            <button onclick="cerrarNotif()" class="hover:bg-white/20 p-1.5 rounded-lg transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div id="notifContent" class="p-4 max-h-[380px] overflow-y-auto">
            <div class="flex items-center justify-center py-8 text-gray-400 text-sm">
                <i class="fas fa-spinner fa-spin mr-2"></i> Cargando...
            </div>
        </div>

        <div class="bg-gray-50 border-t border-gray-200 px-4 py-3 text-center">
            @if($emp && $emp->rol == 4)
                <a href="{{ route('maintenance') }}" class="text-sm text-primary hover:text-blue-700 font-semibold">
                    Ver agenda completa <i class="fas fa-arrow-right ml-1"></i>
                </a>
            @else
                <p class="text-xs text-gray-400">Los mantenimientos son programados por el administrador.</p>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Contenido principal ── --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
        @endif
        @yield('content')
    </main>

    {{-- ── Modal Cambiar Contraseña ── --}}
    <div id="changePasswordModal" class="modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="modal-overlay fixed inset-0 bg-black/75 opacity-0 transition-opacity duration-300 z-40"
             onclick="closeModal('changePasswordModal')"></div>
        <div class="flex items-center justify-center min-h-screen px-4 py-8 relative z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="h-10 w-10 bg-primary/10 rounded-xl flex items-center justify-center">
                        <i class="fas fa-key text-primary"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Cambiar Contraseña</h3>
                        <p class="text-xs text-gray-500">Mínimo 8 caracteres</p>
                    </div>
                </div>
                <div id="passwordMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Contraseña actual</label>
                        <div class="relative">
                            <input type="password" id="currentPassword"
                                   class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none transition">
                            <button type="button" onclick="togglePwd('currentPassword', this)"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nueva contraseña</label>
                        <div class="relative">
                            <input type="password" id="newPassword"
                                   class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none transition">
                            <button type="button" onclick="togglePwd('newPassword', this)"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Confirmar contraseña</label>
                        <div class="relative">
                            <input type="password" id="newPasswordConfirm"
                                   class="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm text-gray-700 focus:ring-2 focus:ring-primary outline-none transition">
                            <button type="button" onclick="togglePwd('newPasswordConfirm', this)"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-5">
                    <button onclick="closeModal('changePasswordModal')"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onclick="saveChangePassword()"
                            class="px-4 py-2 bg-primary hover:bg-blue-900 text-white rounded-lg text-sm font-semibold transition shadow-sm">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Scripts condicionales cargados con Vite --}}
    @if(Auth::guard('empleado')->check())
        @vite(['resources/js/session.js'])
    @endif

    {{-- 4. Pila de scripts para las vistas hijas (@push('scripts')) --}}
    @stack('scripts')
</body>
</html>