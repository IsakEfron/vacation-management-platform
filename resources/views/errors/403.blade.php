<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Denegado — Canel's</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans">
    <div class="text-center max-w-md">
        <div class="text-8xl font-black text-primary mb-4">403</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Acceso Denegado</h1>
        <p class="text-gray-500 mb-6">No tienes permisos para acceder a esta sección.</p>
        <a href="{{ url()->previous() }}"
           class="inline-block bg-primary text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-900 transition">
            Regresar
        </a>
    </div>
</body>
</html>