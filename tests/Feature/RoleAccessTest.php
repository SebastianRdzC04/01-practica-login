<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_access_client_home(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->actingAs($client)->get(route('client.home'));

        $response->assertOk();
        $response->assertSee('Area de cliente');
    }

    public function test_client_cannot_access_dashboard(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_sees_user_dashboard_block_only(): void
    {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Centro de trabajo general');
        $response->assertDontSee('Zona de control administrativo');
        $response->assertDontSee('Logs de autenticacion');
    }

    public function test_admin_sees_admin_dashboard_block_only(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Zona de control administrativo');
        $response->assertDontSee('Centro de trabajo general');
        $response->assertDontSee('Logs de autenticacion');
    }

    public function test_logger_sees_authentication_logs_table(): void
    {
        $logger = User::factory()->logger()->create();

        LoginLog::create([
            'user_id' => $logger->id,
            'event' => LoginLog::EVENT_LOGIN_SUCCESS,
            'succeeded' => true,
            'email' => $logger->email,
            'role' => $logger->role,
            'guard' => 'web',
            'ip_address' => '127.0.0.1',
            'message' => 'Inicio de sesion exitoso.',
        ]);

        $response = $this->actingAs($logger)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Logs de autenticacion');
        $response->assertSee(LoginLog::EVENT_LOGIN_SUCCESS);
        $response->assertDontSee('Zona de control administrativo');
    }
}
