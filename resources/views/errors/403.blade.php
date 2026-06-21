<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Acceso denegado</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-950 text-slate-50 antialiased">
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-center">
            <h1 class="text-8xl font-bold text-red-400">403</h1>
            <p class="mt-4 text-lg text-slate-300">No tienes permiso para acceder a esta pagina.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-full bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">Volver al inicio</a>
        </div>
    </div>
</body>
</html>
