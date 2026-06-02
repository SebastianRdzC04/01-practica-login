<?php

namespace App\Services;

use GuzzleHttp\Client;

class RecaptchaService
{
    /**
     * Verify a reCAPTCHA token using Google's siteverify API.
     * Returns true when there is no secret configured (convenience for local/dev).
     */
    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        $secret = config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET');

        if (! $secret) {
            // If no secret configured, treat as passed to avoid blocking local/dev.
            return true;
        }

        if (! $token) {
            return false;
        }

        $client = new Client(['timeout' => 5]);

        try {
            $res = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                'form_params' => [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ],
            ]);

            $body = json_decode((string) $res->getBody(), true);

            return isset($body['success']) && $body['success'] == true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
