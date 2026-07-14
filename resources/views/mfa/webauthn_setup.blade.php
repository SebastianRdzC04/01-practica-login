<x-guest-layout>
    <div class="max-w-md mx-auto">
        <div class="bg-white p-6">

            <h2 class="text-xl font-semibold text-gray-900 text-center">
                Registrar dispositivo biometrico
            </h2>

            <p class="mt-2 text-sm text-gray-600 text-center">
                Registra tu huella digital, Face ID o Windows Hello como factor adicional de autenticacion.
            </p>

            <div
                id="status"
                class="mt-6 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700 text-center"
            >
                Listo para comenzar.
            </div>

            <div id="diag" class="mt-2 text-xs text-gray-400 text-center break-all"></div>

            <button
                id="start"
                type="button"
                data-options-url="{{ route('mfa.webauthn.options') }}"
                data-register-url="{{ route('mfa.webauthn.register') }}"
                data-home-url="{{ route('home.redirect') }}"
                class="mt-6 w-full px-4 py-3 bg-emerald-100 hover:bg-emerald-50 font-medium rounded-lg transition-colors"
            >
                Registrar dispositivo
            </button>

            <p class="mt-4 text-xs text-center text-gray-500">
                El navegador mostrara una ventana segura para registrar tu dispositivo biometrico.
            </p>

        </div>
    </div>

<script>
const startBtn = document.getElementById('start');
const urls = {
    options: startBtn.dataset.optionsUrl,
    register: startBtn.dataset.registerUrl,
    home: startBtn.dataset.homeUrl,
};

async function b64ToBuffer(base64) {
    base64 = base64.replace(/-/g, '+').replace(/_/g, '/');

    const pad = base64.length % 4;
    if (pad) {
        base64 += '='.repeat(4 - pad);
    }

    const str = atob(base64);
    const buf = new Uint8Array(str.length);

    for (let i = 0; i < str.length; i++) {
        buf[i] = str.charCodeAt(i);
    }

    return buf.buffer;
}

document.getElementById('start').addEventListener('click', async () => {

    const status = document.getElementById('status');
    const button = document.getElementById('start');

    try {

        button.disabled = true;
        button.textContent = 'Procesando...';

        status.textContent =
            'Verificando reCAPTCHA...';

        var recaptchaToken = null;
        if (typeof window.getRecaptchaToken === 'function') {
            recaptchaToken = await window.getRecaptchaToken();
        }

        status.textContent =
            'Solicitando opciones de registro...';

        const resp = await fetch(urls.options, {
            credentials: 'same-origin'
        });

        if (!resp.ok) {
            const err = await resp.json();

            throw new Error(
                err.message || 'Error obteniendo opciones'
            );
        }

        const body = await resp.json();
        const publicKey = body.publicKey;

        publicKey.challenge =
            new Uint8Array(
                await b64ToBuffer(publicKey.challenge)
            );

        publicKey.user.id =
            new Uint8Array(
                await b64ToBuffer(publicKey.user.id)
            );

        status.textContent =
            'Esperando confirmacion del dispositivo biometrico...';

        const cred =
            await navigator.credentials.create({
                publicKey
            });

        const clientDataJSON = btoa(
            String.fromCharCode(
                ...new Uint8Array(
                    cred.response.clientDataJSON
                )
            )
        );

        const attestationObject = btoa(
            String.fromCharCode(
                ...new Uint8Array(
                    cred.response.attestationObject
                )
            )
        );

        const payload = {
            id: cred.id,
            rawId: btoa(
                String.fromCharCode(
                    ...new Uint8Array(cred.rawId)
                )
            ),
            type: cred.type,
            response: {
                clientDataJSON,
                attestationObject
            }
        };

        if (recaptchaToken) {
            payload['g-recaptcha-response'] = recaptchaToken;
        } else if (window._lastRecaptchaToken) {
            payload['g-recaptcha-response'] = window._lastRecaptchaToken;
        }

        status.textContent =
            'Registrando dispositivo...';

        const r = await fetch(urls.register, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name=csrf-token]')
                        .getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        if (!r.ok) {

            const data = await r.json();

            throw new Error(
                data.message || 'Registro fallido'
            );
        }

        status.textContent =
            'Registro completado. Redirigiendo...';

        setTimeout(() => {
            window.location = urls.home;
        }, 800);

    } catch (e) {

        status.textContent =
            e.message || 'Error inesperado';

        button.disabled = false;
        button.textContent =
            'Registrar dispositivo';
    }
});
</script>
</x-guest-layout>

