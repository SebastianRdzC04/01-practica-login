<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    /**
     * Agrega cabeceras de seguridad HTTP a todas las respuestas salientes.
     *
     * Este middleware se ejecuta después de que la aplicación ha generado la respuesta y
     * añade cabeceras de seguridad esenciales para mitigar ataques comunes:
     * - X-Frame-Options: DENY — previene ataques de clickjacking al impedir que la página
     *   sea cargada en un iframe.
     * - X-Content-Type-Options: nosniff — evita que el navegador realice MIME-sniffing,
     *   forzando que interprete los tipos MIME declarados.
     * - Referrer-Policy: strict-origin-when-cross-origin — controla la información enviada
     *   en la cabecera Referer, enviando solo el origen en solicitudes cross-origin.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @return \Illuminate\Http\Response  Respuesta HTTP con las cabeceras de seguridad añadidas.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
