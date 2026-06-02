<x-app-layout>
<div class="max-w-2xl mx-auto py-12">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900">Configurar Autenticación de Dos Factores (TOTP)</h2>
        <p class="mt-2 text-sm text-gray-600">Escanea el código QR con tu app de autenticación (Google Authenticator, Authy, etc.) y luego introduce el código de 6 dígitos para finalizar la activación.</p>

        <div class="mt-6 flex items-center space-x-6">
            <div>
                <img src="{{ $qrImage }}" alt="QR code" class="border" />
            </div>
            <div>
                <p class="text-sm text-gray-700">Clave secreta (guárdala en un lugar seguro):</p>
                <p class="font-mono mt-1">{{ $secret }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('mfa.confirm') }}" class="mt-6">
            @csrf

            <label class="block text-sm font-medium text-gray-700">Código de 6 dígitos</label>
            <input name="totp" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required class="mt-1 block w-48 px-3 py-2 border rounded-md" value="{{ old('totp') }}" />
            @error('totp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror

            <div class="mt-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">Confirmar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>


