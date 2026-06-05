<x-guest-layout>
<div class="max-w-md mx-auto py-12">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900">Autenticar con Windows Hello</h2>
        <p class="mt-2 text-sm text-gray-600">Usa Windows Hello (cara, huella o PIN) para completar la autenticación.</p>

        <div id="status" class="mt-4 text-sm text-gray-700">Preparando...</div>

        <div class="mt-6">
            <button id="auth" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">Autenticar con Windows Hello</button>
        </div>
    </div>
</div>

<script>
async function b64ToBuffer(base64) {
    base64 = base64.replace(/-/g, '+').replace(/_/g, '/');
    const pad = base64.length % 4;
    if (pad) base64 += '='.repeat(4 - pad);
    const str = atob(base64);
    const buf = new Uint8Array(str.length);
    for (let i = 0; i < str.length; i++) buf[i] = str.charCodeAt(i);
    return buf.buffer;
}

document.getElementById('auth').addEventListener('click', async () => {
    const status = document.getElementById('status');
    status.textContent = 'Solicitando opciones de afirmación...';

    const resp = await fetch('{{ route('mfa.webauthn.assertion-options') }}', { credentials: 'same-origin' });
    if (!resp.ok) {
        const err = await resp.json();
        status.textContent = err.message || 'Error obteniendo opciones';
        return;
    }
    const body = await resp.json();
    const publicKey = body.publicKey;

    publicKey.challenge = new Uint8Array(await b64ToBuffer(publicKey.challenge));
    if (publicKey.allowCredentials) {
        publicKey.allowCredentials = publicKey.allowCredentials.map(c => ({ type: c.type, id: new Uint8Array(atob(c.id).split('').map(ch => ch.charCodeAt(0))) }));
    }

    status.textContent = 'Abriendo diálogo de autenticación (Windows Hello)...';

    try {
        const assertion = await navigator.credentials.get({ publicKey });

        const clientDataJSON = btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON)));
        const authenticatorData = btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData)));
        const signature = btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature)));
        const userHandle = assertion.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(assertion.response.userHandle))) : null;

        const payload = {
            id: assertion.id,
            rawId: btoa(String.fromCharCode(...new Uint8Array(assertion.rawId))),
            type: assertion.type,
            response: {
                clientDataJSON,
                authenticatorData,
                signature,
                userHandle,
            }
        };

        status.textContent = 'Enviando afirmación al servidor...';
        const r = await fetch('{{ route('mfa.webauthn.authenticate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!r.ok) {
            const data = await r.json();
            status.textContent = data.message || 'Autenticación fallida';
            return;
        }

        status.textContent = 'Autenticación completada. Redirigiendo...';
        setTimeout(() => window.location = '{{ route('home.redirect') }}', 800);
    } catch (e) {
        status.textContent = 'Error: ' + e.message;
    }
});
</script>
</x-guest-layout>
