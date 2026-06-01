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
                    @include('dashboard.partials.user-panel')
                @endif

                @if ($user->hasRole(\App\Models\User::ROLE_ADMIN))
                    @include('dashboard.partials.admin-panel')
                @endif

                @if ($user->hasRole(\App\Models\User::ROLE_LOGGER))
                    @include('dashboard.partials.logger-panel')
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
