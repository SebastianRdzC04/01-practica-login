<?php

namespace Tests\Feature\Auth;

use App\Support\LoginLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_view_exposes_current_lockout_state(): void
    {
        $email = 'blocked@example.com';
        $throttleKey = LoginLockout::throttleKey($email, '127.0.0.1');

        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);

        $response = $this->from('/login?email='.$email)->get('/login?email='.$email);

        $response->assertOk();
        $response->assertSee('El acceso esta temporalmente bloqueado', false);
    }

    public function test_login_lockout_status_endpoint_returns_remaining_time(): void
    {
        $email = 'blocked@example.com';
        $throttleKey = LoginLockout::throttleKey($email, '127.0.0.1');

        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);

        $response = $this->getJson(route('login.lockout-status', ['email' => $email]));

        $response->assertOk();
        $response->assertJsonPath('locked', true);
        $response->assertJsonPath('max_attempts', LoginLockout::MAX_ATTEMPTS);
        $this->assertGreaterThan(0, $response->json('seconds_remaining'));
    }
}
