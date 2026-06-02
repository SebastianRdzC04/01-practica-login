<x-guest-layout>
<div class="max-w-md mx-auto py-12">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900">Verificación de Dos Factores</h2>
        <p class="mt-2 text-sm text-gray-600">Introduce el código de 6 dígitos de tu app de autenticación.</p>

        <form method="POST" action="{{ route('mfa.verify.post') }}" class="mt-6">
            @csrf

            <label class="block text-sm font-medium text-gray-700">Código de 6 dígitos</label>
            <input name="totp" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required class="mt-1 block w-48 px-3 py-2 border rounded-md" value="{{ old('totp') }}" />
            @error('totp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror

            <div class="mt-4 flex items-center justify-between">
                <div>
                    <x-recaptcha />
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">Verificar</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500">Cerrar sesión</button>
                </form>
            </div>
        </form>
    </div>
</div>
</x-guest-layout>
