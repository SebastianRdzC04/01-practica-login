<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 - Sesion expirada</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-950 text-slate-50 antialiased">
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-center">
            <h1 class="text-8xl font-bold text-orange-400">419</h1>
            <p class="mt-4 text-lg text-slate-300">La sesion ha expirado. Vuelve a iniciar sesion.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-full bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">Ir al login</a>
        </div>
    </div>
</body>
</html>
