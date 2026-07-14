<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; img-src 'self' data: https:; font-src 'self' https://fonts.bunny.net; frame-src https://www.google.com https://recaptcha.google.com https://www.recaptcha.net; connect-src 'self' https://www.google.com https://www.recaptcha.net https://static.cloudflareinsights.com;">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @php
            $recaptchaSiteKey = config('services.recaptcha.sitekey') ?? env('RECAPTCHA_SITE_KEY');
        @endphp
        @if ($recaptchaSiteKey)
            <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>
            <script type="text/javascript">
                window._recaptchaWidgetId = null;
                window._recaptchaReady = false;
                window._recaptchaRequestingForm = null;

                function onRecaptchaLoad() {
                    if (!window.grecaptcha) return;
                    grecaptcha.ready(function() {
                        window._recaptchaReady = true;
                        try {
                            window._recaptchaWidgetId = grecaptcha.render('recaptcha-container', {
                                'sitekey': '{{ $recaptchaSiteKey }}',
                                'size': 'invisible',
                                'badge': 'bottomright',
                                'callback': function(token) {
                                    var form = window._recaptchaRequestingForm;
                                    if (form) {
                                        var input = form.querySelector('input[name="g-recaptcha-response"]');
                                        if (!input) {
                                            input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.name = 'g-recaptcha-response';
                                            form.appendChild(input);
                                        }
                                        input.value = token;
                                        window._recaptchaRequestingForm = null;
                                        form.submit();
                                    }
                                }
                            });
                        } catch (e) {
                            console.error('reCAPTCHA render error', e);
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    function attachRecaptchaToForm(form) {
                        if (form.__recaptchaAttached) return;
                        form.__recaptchaAttached = true;
                        form.addEventListener('submit', function(e) {
                            var existing = form.querySelector('input[name="g-recaptcha-response"]');
                            if (existing && existing.value) return true;
                            e.preventDefault();
                            window._recaptchaRequestingForm = form;
                            if (window._recaptchaReady && window._recaptchaWidgetId !== null) {
                                grecaptcha.execute(window._recaptchaWidgetId);
                            } else {
                                var iv = setInterval(function() {
                                    if (window._recaptchaReady && window._recaptchaWidgetId !== null) {
                                        clearInterval(iv);
                                        grecaptcha.execute(window._recaptchaWidgetId);
                                    }
                                }, 200);
                            }
                        });
                    }

                    var forms = document.querySelectorAll('form.recaptcha-invisible');
                    forms.forEach(function(f) { attachRecaptchaToForm(f); });
                });
            </script>
            <div id="recaptcha-container" style="display:none"></div>
        @endif
    </head>
    <body
        class="font-sans antialiased"
        @auth
            @php
                $inactivityProtection = [
                    'enabled' => (bool) session('inactivity.protected', false),
                    'modalTimeoutSeconds' => (int) session('inactivity.modal_timeout_seconds', 30),
                    'warningTimeoutSeconds' => (int) session('inactivity.warning_timeout_seconds', 10),
                    'heartbeatUrl' => route('session.activity'),
                    'logoutUrl' => route('logout'),
                    'csrfToken' => csrf_token(),
                ];
            @endphp
            x-data="sessionInactivityGuard(@js($inactivityProtection))"
            x-init="init()"
        @endauth
    >
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @auth
            <div
                x-cloak
                x-show="enabled && showPrompt"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4"
            >
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h2 class="text-lg font-semibold text-slate-900">Aun estas ahi?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Detectamos inactividad en tu sesion. Si deseas continuar, confirma antes de que termine la cuenta regresiva.
                    </p>
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                        La sesion se cerrara automaticamente en <span x-text="promptCountdown"></span>.
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-100"
                            x-on:click="logoutNow()"
                        >
                            Cerrar sesion
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                            x-on:click="stayActive()"
                        >
                            Seguir en sesion
                        </button>
                    </div>
                </div>
            </div>
        @endauth
    </body>
</html>
