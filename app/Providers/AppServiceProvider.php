<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('local')) {
            if (! $this->app->runningInConsole()) {
                $request = $this->app['request'];
                URL::forceRootUrl($request->getSchemeAndHttpHost());
                URL::forceScheme($request->getScheme());
            }
        } else {
            $appUrl = config('app.url');
            if ($appUrl) {
                URL::forceRootUrl($appUrl);
                $scheme = parse_url($appUrl, PHP_URL_SCHEME);
                if ($scheme) {
                    URL::forceScheme($scheme);
                }
            }
        }

        Password::defaults(function () {
            return Password::min(10)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }
}
