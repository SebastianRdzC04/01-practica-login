<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        //
    ];

    protected function addCookieToResponse($request, $response)
    {
        $config = config('session');

        $response->headers->setCookie(
            new Cookie(
                'XSRF-APP-TOKEN',
                $request->session()->token(),
                $this->availableAt(60 * 24 * 365),
                $config['path'],
                $config['domain'],
                $config['secure'],
                false,
                false,
                $config['same_site'] ?? null,
                $config['partitioned'] ?? false
            )
        );

        return $response;
    }
}