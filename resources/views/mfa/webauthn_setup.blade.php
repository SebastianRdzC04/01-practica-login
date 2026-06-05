<x-guest-layout>
<div class="max-w-2xl mx-auto py-12">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900">Configurar Windows Hello (WebAuthn)</h2>
        <p class="mt-2 text-sm text-gray-600">Registra Windows Hello (cara, huella, PIN) como tercer factor para el admin. Usa un dispositivo/ navegador que soporte WebAuthn (Edge, Chrome, etc.).</p>

        <div id="status" class="mt-4 text-sm text-gray-700">Preparando...</div>

        <div class="mt-6">
            <button id="start" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">Registrar Windows Hello</button>
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

document.getElementById('start').addEventListener('click', async () => {
    const status = document.getElementById('status');
    status.textContent = 'Solicitando opciones al servidor...';

    const resp = await fetch('{{ route('mfa.webauthn.options') }}', { credentials: 'same-origin' });
    if (!resp.ok) {
        const err = await resp.json();
        status.textContent = err.message || 'Error obteniendo opciones';
        return;
    }
    const body = await resp.json();
    const publicKey = body.publicKey;

    // convert base64 challenge and user.id
    publicKey.challenge = new Uint8Array(await b64ToBuffer(publicKey.challenge));
    publicKey.user.id = new Uint8Array(await b64ToBuffer(publicKey.user.id));

    status.textContent = 'Abriendo diálogo de registro (Windows Hello)...';

    try {
        const cred = await navigator.credentials.create({ publicKey });

        // prepare attestation to send to server
        const clientDataJSON = btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON)));
        const attestationObject = btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject)));

        const payload = {
            id: cred.id,
            rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
            type: cred.type,
            response: {
                clientDataJSON,
                attestationObject,
            }
        };

        status.textContent = 'Enviando registro al servidor...';
        const r = await fetch('{{ route('mfa.webauthn.register') }}', {
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
            status.textContent = data.message || 'Registro fallido';
            return;
        }

        status.textContent = 'Registro exitoso. Redirigiendo para completar autenticación...';
        setTimeout(() => window.location = '{{ route('mfa.webauthn.auth') }}', 800);
    } catch (e) {
        status.textContent = 'Error: ' + e.message;
    }
});
</script>
</x-guest-layout>
