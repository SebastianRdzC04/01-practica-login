<x-guest-layout>
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white shadow-lg rounded-2xl p-8">

            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900">
                    Verificación de Dos Factores
                </h2>

                <p class="mt-3 text-sm text-gray-600">
                    Introduce el código generado por tu aplicación de autenticación
                    para continuar.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('mfa.verify.post') }}"
                class="mt-8 recaptcha-invisible"
            >
                @csrf

                <div>
                    <label
                        for="totp"
                        class="block text-sm font-medium text-gray-700"
                    >
                        Código de verificación
                    </label>

                    <input
                        id="totp"
                        name="totp"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                        value="{{ old('totp') }}"
                        placeholder="000000"
                        class="mt-2 block w-full text-center text-2xl tracking-[0.5em] px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />

                    @error('totp')
                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="mt-8 w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors duration-200"
                >
                    Verificar
                </button>
            </form>

            <div class="mt-6 border-t pt-4 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-guest-layout>
