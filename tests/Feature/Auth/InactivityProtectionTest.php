<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\InactivityProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivityProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_session_is_marked_as_inactivity_protected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertTrue(session(InactivityProtection::SESSION_KEY_PROTECTED));
        $this->assertSame(30, session(InactivityProtection::SESSION_KEY_MODAL_TIMEOUT_SECONDS));
        $this->assertSame(10, session(InactivityProtection::SESSION_KEY_WARNING_TIMEOUT_SECONDS));
        $this->assertSame(300, session(InactivityProtection::SESSION_KEY_SERVER_TIMEOUT_SECONDS));
    }

    public function test_client_session_is_not_marked_as_inactivity_protected(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.home'))
            ->assertOk();

        $this->assertFalse(session()->has(InactivityProtection::SESSION_KEY_PROTECTED));
    }

    public function test_protected_session_heartbeat_refreshes_last_activity(): void
    {
        $logger = User::factory()->logger()->create();

        $this->actingAs($logger)->get(route('dashboard'));

        session()->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->subMinutes(2)->timestamp);
        $previousValue = session(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT);

        $response = $this->actingAs($logger)->postJson(route('session.activity'));

        $response->assertOk();
        $response->assertJsonPath('protected', true);
        $this->assertGreaterThan($previousValue, session(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT));
    }

    public function test_protected_session_is_closed_server_side_when_timeout_is_exceeded(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'));

        session()->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->subSeconds(301)->timestamp);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Tu sesion se cerro por inactividad.');
        $this->assertGuest();
    }
}
