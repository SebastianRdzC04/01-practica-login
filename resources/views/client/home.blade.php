<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Area de cliente
            </h2>

            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ $user->role }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-600 shadow-xl">
                <div class="p-8 sm:p-12 text-white">
                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-100">Vista exclusiva</p>
                    <h3 class="mt-4 text-3xl font-semibold">Bienvenido, {{ $user->name }}</h3>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-emerald-50">
                        Esta es la vista simple reservada para clientes. El flujo de registro publico crea automaticamente este rol y lo redirige aqui despues de autenticarse.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
