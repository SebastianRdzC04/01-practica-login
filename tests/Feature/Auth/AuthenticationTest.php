<?php

namespace Tests\Feature\Auth;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->user()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('login_logs', [
            'event' => LoginLog::EVENT_LOGIN_SUCCESS,
            'email' => $user->email,
            'succeeded' => true,
        ]);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->client()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();

        $this->assertDatabaseHas('login_logs', [
            'event' => LoginLog::EVENT_LOGIN_FAILED,
            'email' => $user->email,
            'succeeded' => false,
        ]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');

        $this->assertDatabaseHas('login_logs', [
            'event' => LoginLog::EVENT_LOGOUT,
            'email' => $user->email,
            'succeeded' => true,
        ]);
    }

    public function test_clients_are_redirected_to_their_private_area_after_login(): void
    {
        $user = User::factory()->client()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('client.home'));
    }
}
