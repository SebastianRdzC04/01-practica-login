<x-guest-layout>
    <div class="max-w-md mx-auto">
        <div class="bg-white p-6">

            <h2 class="text-xl font-semibold text-gray-900 text-center">
                Windows Hello
            </h2>

            <p class="mt-2 text-sm text-gray-600 text-center">
                Utiliza reconocimiento facial, huella digital o PIN para completar la autenticación.
            </p>

            <div
                id="status"
                class="mt-6 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700 text-center"
            >
                Listo para autenticar.
            </div>

            <button
                id="auth"
                type="button"
                data-assertion-url="{{ route('mfa.webauthn.assertion-options') }}"
                data-authenticate-url="{{ route('mfa.webauthn.authenticate') }}"
                data-home-url="{{ route('home.redirect') }}"
                class="mt-6 w-full px-4 py-3 bg-emerald-100 hover:bg-emerald-50 font-medium rounded-lg transition-colors"
            >
                Autenticar con Windows Hello
            </button>

            <p class="mt-4 text-xs text-center text-gray-500">
                Se abrirá una ventana segura de Windows para verificar tu identidad.
            </p>

        </div>
    </div>

<script>
const authBtn = document.getElementById('auth');
const urls = {
    assertion: authBtn.dataset.assertionUrl,
    authenticate: authBtn.dataset.authenticateUrl,
    home: authBtn.dataset.homeUrl,
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

document.getElementById('auth').addEventListener('click', async () => {

    const status = document.getElementById('status');
    const button = document.getElementById('auth');

    try {

        button.disabled = true;
        button.textContent = 'Procesando...';

        status.textContent =
            'Solicitando opciones de autenticación...';

        const resp = await fetch(urls.assertion, {
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

        if (publicKey.allowCredentials) {
            publicKey.allowCredentials =
                await Promise.all(
                    publicKey.allowCredentials.map(async c => ({
                        type: c.type,
                        id: new Uint8Array(
                            await b64ToBuffer(c.id)
                        )
                    }))
                );
        }

        status.textContent =
            'Esperando confirmación de Windows Hello...';

        const assertion =
            await navigator.credentials.get({
                publicKey
            });

        const clientDataJSON = btoa(
            String.fromCharCode(
                ...new Uint8Array(
                    assertion.response.clientDataJSON
                )
            )
        );

        const authenticatorData = btoa(
            String.fromCharCode(
                ...new Uint8Array(
                    assertion.response.authenticatorData
                )
            )
        );

        const signature = btoa(
            String.fromCharCode(
                ...new Uint8Array(
                    assertion.response.signature
                )
            )
        );

        const userHandle =
            assertion.response.userHandle
                ? btoa(
                    String.fromCharCode(
                        ...new Uint8Array(
                            assertion.response.userHandle
                        )
                    )
                )
                : null;

        const payload = {
            id: assertion.id,
            rawId: btoa(
                String.fromCharCode(
                    ...new Uint8Array(assertion.rawId)
                )
            ),
            type: assertion.type,
            response: {
                clientDataJSON,
                authenticatorData,
                signature,
                userHandle
            }
        };

        status.textContent =
            'Verificando identidad...';

        const r = await fetch(urls.authenticate, {
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
                data.message || 'Autenticación fallida'
            );
        }

        status.textContent =
            'Autenticación completada. Redirigiendo...';

        setTimeout(() => {
            window.location = urls.home;
        }, 800);

    } catch (e) {

        status.textContent =
            e.message || 'Error inesperado';

        button.disabled = false;
        button.textContent =
            'Autenticar con Windows Hello';
    }
});
</script>
</x-guest-layout>
