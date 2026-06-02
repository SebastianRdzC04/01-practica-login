@if (config('services.recaptcha.sitekey') ?? env('RECAPTCHA_SITE_KEY'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.sitekey') ?? env('RECAPTCHA_SITE_KEY') }}"></div>
@endif
@if(config('services.recaptcha.sitekey') || env('RECAPTCHA_SITE_KEY'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <div class="mt-4">
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.sitekey') ?? env('RECAPTCHA_SITE_KEY') }}"></div>
    </div>
@endif
