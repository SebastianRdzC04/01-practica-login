<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .grecaptcha-badge { bottom: 20px !important; box-shadow: 0 2px 8px rgba(0,0,0,.12) !important; border-radius: 4px !important; }
        </style>
        @php
            $recaptchaSiteKey = config('services.recaptcha.sitekey') ?? env('RECAPTCHA_SITE_KEY');
        @endphp
        @if ($recaptchaSiteKey)
            <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>
            <script type="text/javascript">
                window._recaptchaWidgetId = null;
                window._recaptchaReady = false;
                window._recaptchaRequestingForm = null;
                window._lastRecaptchaToken = null;

                window.getRecaptchaToken = function() {
                    return new Promise(function(resolve) {
                        if (!window._recaptchaReady || window._recaptchaWidgetId === null) {
                            resolve(null);
                            return;
                        }
                        var prevCallback = window._recaptchaTokenResolve;
                        window._recaptchaTokenResolve = resolve;
                        grecaptcha.execute(window._recaptchaWidgetId);
                    });
                };

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
                                    window._lastRecaptchaToken = token;
                                    if (window._recaptchaTokenResolve) {
                                        var resolve = window._recaptchaTokenResolve;
                                        window._recaptchaTokenResolve = null;
                                        resolve(token);
                                        return;
                                    }
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
                            // if token already present, allow submit
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
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="text-center">
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
                <div class="mt-2">
                    <a href="/" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        &larr; Volver al inicio
                    </a>
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
