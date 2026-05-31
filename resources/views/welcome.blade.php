<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-50 antialiased">
        <div class="min-h-screen">
            <header class="border-b border-white/10">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">Laravel 10</p>
                        <h1 class="mt-2 text-2xl font-semibold">Practica de login con roles</h1>
                    </div>

                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ route(auth()->user()->homeRouteName()) }}" class="rounded-full bg-cyan-400 px-5 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                Ir a mi area
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-full border border-white/15 px-5 py-2 text-sm font-semibold text-white transition hover:border-cyan-300 hover:text-cyan-200">
                                Iniciar sesion
                            </a>
                            <a href="{{ route('register') }}" class="rounded-full bg-cyan-400 px-5 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                Registrarse
                            </a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="mx-auto grid max-w-7xl gap-12 px-6 py-16 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                <section>
                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300">Seguridad y desarrollo</p>
                    <h2 class="mt-5 max-w-3xl text-5xl font-semibold leading-tight text-white">
                        Autenticacion con Laravel 10, roles separados y trazabilidad de sesiones.
                    </h2>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                        El registro publico crea clientes. Usuario, administrador y logger entran a un dashboard comun con contenido exclusivo por rol. Los intentos de sesion quedan auditados para crecer sin rehacer la base.
                    </p>

                    <div class="mt-10 flex flex-wrap gap-4 text-sm text-slate-200">
                        <div class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-4 py-2">cliente</div>
                        <div class="rounded-full border border-blue-400/40 bg-blue-400/10 px-4 py-2">usuario</div>
                        <div class="rounded-full border border-amber-400/40 bg-amber-400/10 px-4 py-2">administrador</div>
                        <div class="rounded-full border border-fuchsia-400/40 bg-fuchsia-400/10 px-4 py-2">logger</div>
                    </div>
                </section>

                <section class="grid gap-4">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <p class="text-sm font-semibold text-cyan-300">Flujo principal</p>
                        <ul class="mt-4 space-y-3 text-sm text-slate-300">
                            <li>Registro web solo para clientes.</li>
                            <li>Login compartido para los cuatro roles.</li>
                            <li>Middleware por rol y logs estructurados.</li>
                            <li>Visualizacion Blade con Tailwind.</li>
                        </ul>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <p class="text-sm font-semibold text-cyan-300">Observabilidad</p>
                        <p class="mt-4 text-sm leading-7 text-slate-300">
                            Los accesos a vistas protegidas y los eventos de login, fallo, bloqueo y logout se registran en archivo y tambien en la terminal del servidor mediante logging nativo de Laravel.
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
