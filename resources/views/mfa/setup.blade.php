<x-guest-layout>
    <div class="w-full max-w-xl mx-auto">
        <div class="bg-white shadow-lg rounded-2xl p-8">

            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900">
                    Configurar Autenticación de Dos Factores
                </h2>

                <p class="mt-3 text-sm text-gray-600">
                    Escanea el código QR con Google Authenticator, Authy o cualquier
                    aplicación compatible con TOTP. Después introduce el código de
                    verificación para completar la activación.
                </p>
            </div>

            <div class="mt-8 flex flex-col items-center">

                <div class="p-4 bg-gray-50 border rounded-xl">
                    <img
                        src="{{ $qrImage }}"
                        alt="Código QR MFA"
                        class="w-56 h-56"
                    />
                </div>

                <div class="mt-6 w-full">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Clave secreta de respaldo
                    </label>

                    <div
                        id="secret-box"
                        class="bg-gray-100 border rounded-lg p-4 cursor-pointer hover:bg-gray-200 transition-colors text-center"
                        onclick="copySecret()"
                        title="Presiona para copiar"
                    >
                        <p id="secret-text" class="font-mono text-sm break-all text-gray-800 select-all">
                            {{ $secret }}
                        </p>
                        <p id="copy-msg" class="mt-1 text-xs text-emerald-600 hidden">
                            Copiado
                        </p>
                    </div>

                    <script>
                    function copySecret() {
                        var text = document.getElementById('secret-text');
                        if (!text) return;
                        var range = document.createRange();
                        range.selectNodeContents(text);
                        var sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                        try {
                            document.execCommand('copy');
                            sel.removeAllRanges();
                            var msg = document.getElementById('copy-msg');
                            if (msg) {
                                msg.classList.remove('hidden');
                                setTimeout(function() { msg.classList.add('hidden'); }, 2000);
                            }
                        } catch(e) {}
                    }
                    </script>

                    <p class="mt-2 text-xs text-gray-500">
                        Guarda esta clave en un lugar seguro. Te permitirá recuperar
                        el acceso si pierdes tu dispositivo autenticador.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('mfa.confirm') }}"
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
                        value="{{ old('totp') }}"
                        required
                        class="mt-2 block w-full text-center text-2xl tracking-[0.5em] px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="000000"
                    />

                    @error('totp')
                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="mt-8">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors duration-200"
                    >
                        Confirmar y Activar MFA
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
