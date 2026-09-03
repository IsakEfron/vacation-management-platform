<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Canel's</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('img/canels-icon.png') }}">
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12 px-4 font-sans">
<div class="max-w-md w-full space-y-6">

    <div class="text-center">
        <img class="mx-auto h-16 w-auto object-contain mb-4" src="{{ asset('img/canels-Logo.png') }}" alt="Logo Canels">
    </div>

    <div class="bg-white py-8 px-6 shadow-2xl rounded-2xl border-t-4 border-primary sm:px-10">

        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center h-20 w-20 bg-gray-100 rounded-full text-gray-400 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-primary">Iniciar Sesión</h2>
            <p class="text-sm text-gray-500 mt-1">Ingresa tus credenciales para acceder</p>
        </div>

        {{-- Mensajes de error / bloqueo --}}
        @if(session('bloqueo_tipo') === 'permanente')
        <div class="mb-5 p-4 bg-red-50 border border-red-300 rounded-xl flex items-start gap-3">
            <div class="flex-shrink-0 h-8 w-8 bg-red-100 rounded-full flex items-center justify-center">
                <svg class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524L13.476 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-red-800">Cuenta bloqueada</p>
                <p class="text-xs text-red-600 mt-0.5">{{ $errors->first('usuario') }}</p>
            </div>
        </div>

        @elseif(session('bloqueo_tipo') === 'temporal')
        <div class="mb-5 p-4 bg-orange-50 border border-orange-300 rounded-xl">
            <div class="flex items-start gap-3 mb-3">
                <div class="flex-shrink-0 h-8 w-8 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg class="h-5 w-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-orange-800">Acceso temporalmente suspendido</p>
                    <p class="text-xs text-orange-600 mt-0.5">{{ $errors->first('usuario') }}</p>
                </div>
            </div>
            <div class="bg-orange-100 rounded-lg p-3">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs text-orange-700 font-medium">Tiempo restante</span>
                    <span id="timerDisplay" class="text-sm font-bold text-orange-800">2:00</span>
                </div>
                <div class="w-full bg-orange-200 rounded-full h-2">
                    <div id="timerBar" class="bg-orange-500 h-2 rounded-full transition-all duration-1000" style="width:100%"></div>
                </div>
            </div>
        </div>

        @elseif($errors->has('usuario'))
        <div class="mb-5 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700">{{ $errors->first('usuario') }}</p>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-5 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" id="loginForm" class="space-y-5">
            @csrf

            <div>
                <label for="usuario" class="block text-sm font-medium text-gray-700 mb-1">Número de Nómina</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <input id="usuario" name="usuario" type="text" required autofocus
                           value="{{ old('usuario') }}"
                           @if(session('bloqueo_tipo') === 'permanente') disabled @endif
                           class="block w-full pl-10 pr-3 py-3 border @error('usuario') border-red-400 bg-red-50 @else border-gray-300 @enderror rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition disabled:opacity-50 disabled:cursor-not-allowed"
                           placeholder="Ej. 323232">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <input id="password" name="password" type="password" required
                           @if(session('bloqueo_tipo') === 'permanente') disabled @endif
                           class="block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition disabled:opacity-50 disabled:cursor-not-allowed"
                           placeholder="••••••••">
                    <button type="button" id="btnTogglePwd" onclick="toggleLoginPwd()"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 transition"
                            tabindex="-1" title="Mostrar/ocultar contraseña">
                        <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" id="btnLogin"
                    @if(session('bloqueo_tipo') === 'permanente') disabled @endif
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-primary hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition transform hover:scale-[1.01] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                ENTRAR
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-500">&copy; {{ date('Y') }} Canel's. Todos los derechos reservados.</p>
</div>

{{-- JS mínimo e inline: solo funciones específicas de esta pantalla --}}
<script>
function toggleLoginPwd() {
    const input  = document.getElementById('password');
    const eyeOn  = document.getElementById('iconEye');
    const eyeOff = document.getElementById('iconEyeOff');
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    eyeOn.classList.toggle('hidden', !showing);
    eyeOff.classList.toggle('hidden', showing);
}
</script>

@if(session('bloqueo_tipo') === 'temporal')
<script>
(function () {
    const SEG  = {{ session('bloqueo_segundos', 120) }};
    const bar  = document.getElementById('timerBar');
    const disp = document.getElementById('timerDisplay');

    document.getElementById('loginForm')
        .querySelectorAll('input, button')
        .forEach(el => el.disabled = true);

    let r = SEG;
    const iv = setInterval(() => {
        r--;
        const m = String(Math.floor(r / 60)).padStart(2, '0');
        const s = String(r % 60).padStart(2, '0');
        if (disp) disp.textContent = `${m}:${s}`;
        if (bar)  bar.style.width  = `${(r / SEG) * 100}%`;
        if (r <= 0) {
            clearInterval(iv);
            document.getElementById('loginForm')
                .querySelectorAll('input, button')
                .forEach(el => el.disabled = false);
            const barWrap = bar?.closest('.bg-orange-100');
            if (barWrap) barWrap.innerHTML = '<p class="text-xs text-green-700 font-bold text-center">✓ Ya puedes intentar de nuevo</p>';
        }
    }, 1000);
})();
</script>
@endif

</body>
</html>