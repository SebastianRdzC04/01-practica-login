<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="p-6 text-gray-900 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Rol usuario</p>
        <h3 class="mt-3 text-2xl font-semibold">Centro de trabajo general</h3>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600">
            Este componente se carga solo para el rol <strong>usuario</strong>. Queda separado para que puedas crecer este dashboard sin seguir metiendo condiciones largas en la vista principal.
        </p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-slate-50 p-5">
                <h4 class="text-sm font-semibold text-slate-900">Acceso operativo</h4>
                <p class="mt-2 text-sm text-slate-600">
                    Ideal para colocar accesos rapidos, acciones frecuentes o modulos de uso diario.
                </p>
            </div>

            <div class="rounded-2xl bg-indigo-50 p-5">
                <h4 class="text-sm font-semibold text-indigo-950">Estado del espacio</h4>
                <p class="mt-2 text-sm text-indigo-700">
                    La vista del usuario ya esta aislada como componente independiente dentro de `/dashboard`.
                </p>
            </div>
        </div>
    </div>
</div>
