{{-- ── Panel de Notificaciones de Mantenimiento ── --}}
{{-- @include('partials.notificaciones') en layouts/app.blade.php antes de </body> --}}

<div id="notifPanel"
     class="hidden fixed top-20 right-4 md:right-8 z-50 bg-white rounded-2xl shadow-2xl border border-gray-200 w-full md:w-[420px] max-w-[95vw] overflow-hidden"
     style="transition: opacity 0.25s ease, transform 0.25s ease; opacity:0; transform:translateY(-10px)">

    <div class="bg-gradient-to-r from-primary to-blue-600 text-white px-5 py-4 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <i class="fas fa-calendar-alt text-lg"></i>
            <span class="font-bold">Mantenimientos Programados</span>
        </div>
        <button onclick="cerrarNotif()" class="hover:bg-white/20 p-1.5 rounded-lg transition">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div id="notifContent" class="p-4 max-h-[400px] overflow-y-auto">
        <div class="flex items-center justify-center py-8 text-gray-400">
            <i class="fas fa-spinner fa-spin text-xl mr-2"></i> Cargando...
        </div>
    </div>

    <div class="bg-gray-50 border-t border-gray-200 px-4 py-3 text-center">
        @if(Auth::guard('empleado')->check() && Auth::guard('empleado')->user()?->rol == 4)
        <a href="{{ route('maintenance') }}" class="text-sm text-primary hover:text-blue-700 font-semibold">
            Ver agenda completa <i class="fas fa-arrow-right ml-1"></i>
        </a>
        @else
        <p class="text-xs text-gray-400">Los mantenimientos son programados por el administrador del sistema.</p>
        @endif
    </div>
</div>