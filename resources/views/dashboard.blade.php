<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>

            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ $user->role }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6">
                <div class="overflow-hidden rounded-2xl bg-slate-900 shadow-sm">
                    <div class="p-6 sm:p-8 text-white">
                        <p class="text-sm uppercase tracking-[0.2em] text-slate-300">Panel privado</p>
                        <h3 class="mt-3 text-3xl font-semibold">Bienvenido, {{ $user->name }}</h3>
                        <p class="mt-3 max-w-3xl text-sm text-slate-300">
                            Has iniciado sesion correctamente en tu espacio protegido construido con Laravel 10, Breeze y Blade.
                        </p>
                    </div>
                </div>

                @if ($user->hasRole(\App\Models\User::ROLE_USER))
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-semibold">Inicio de usuario</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                Este bloque solo se muestra al rol <strong>usuario</strong>. Aqui puedes arrancar futuras funcionalidades del panel general.
                            </p>
                        </div>
                    </div>
                @endif

                @if ($user->hasRole(\App\Models\User::ROLE_ADMIN))
                    <div class="overflow-hidden rounded-2xl bg-amber-50 shadow-sm ring-1 ring-amber-200">
                        <div class="p-6 text-amber-950">
                            <h3 class="text-lg font-semibold">Componente exclusivo de administrador</h3>
                            <p class="mt-2 text-sm text-amber-800">
                                Esta tarjeta solo la ve el rol <strong>administrador</strong>. Queda lista para crecer con controles de gestion, revision y configuracion.
                            </p>
                        </div>
                    </div>
                @endif

                @if ($user->hasRole(\App\Models\User::ROLE_LOGGER))
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div class="border-b border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900">Logs de autenticacion</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                Esta tabla la ve solo el rol <strong>logger</strong> y ya esta preparada para crecer con mas contexto y filtros.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-6 py-4">Fecha</th>
                                        <th class="px-6 py-4">Evento</th>
                                        <th class="px-6 py-4">Email</th>
                                        <th class="px-6 py-4">Rol</th>
                                        <th class="px-6 py-4">IP</th>
                                        <th class="px-6 py-4">Estado</th>
                                        <th class="px-6 py-4">Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white text-gray-700">
                                    @forelse ($loginLogs as $log)
                                        <tr>
                                            <td class="whitespace-nowrap px-6 py-4">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ $log->event }}</td>
                                            <td class="px-6 py-4">{{ $log->email ?? 'N/D' }}</td>
                                            <td class="px-6 py-4">{{ $log->role ?? 'N/D' }}</td>
                                            <td class="px-6 py-4">{{ $log->ip_address ?? 'N/D' }}</td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->succeeded ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                                    {{ $log->succeeded ? 'exitoso' : 'fallido' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">{{ $log->message ?? 'Sin detalle' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">Todavia no hay eventos de autenticacion registrados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-gray-200 px-6 py-4">
                            {{ $loginLogs->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
