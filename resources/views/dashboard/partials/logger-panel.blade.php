<div class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="border-b border-gray-200 p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Rol logger</p>
        <h3 class="mt-3 text-2xl font-semibold text-gray-900">Logs de autenticacion</h3>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600">
            Este componente se muestra solo para <strong>logger</strong> y concentra el historial de eventos de autenticacion sin mezclarlo con otros roles.
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
