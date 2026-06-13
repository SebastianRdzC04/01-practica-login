<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\InactivityProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivityProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpTotpUser(User $user): User
    {
        $user->two_factor_secret = encrypt(str_repeat('A', 32));
        $user->two_factor_enabled = true;
        $user->save();

        return $user;
    }

    protected function setUpAdminWithMfa(User $user): User
    {
        $user->two_factor_secret = encrypt(str_repeat('A', 32));
        $user->two_factor_enabled = true;
        $user->save();

        $credential = new \Laragear\WebAuthn\Models\WebAuthnCredential();
        $credential->id = \Illuminate\Support\Str::random(50);
        $credential->user_id = (\Illuminate\Support\Str::uuid())->toString();
        $credential->rp_id = 'localhost';
        $credential->origin = 'http://localhost';
        $credential->public_key = 'test_public_key';

        $user->webAuthnCredentials()->save($credential);

        return $user;
    }

    protected function actingAsWithMfa(User $user, array $factors = ['totp']): static
    {
        session(['factors_passed' => $factors]);

        return $this->actingAs($user);
    }

    public function test_admin_session_is_marked_as_inactivity_protected(): void
    {
        $admin = $this->setUpAdminWithMfa(User::factory()->admin()->create());

        $this->actingAsWithMfa($admin, ['totp', 'webauthn'])
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
        $logger = $this->setUpTotpUser(User::factory()->logger()->create());

        $this->actingAsWithMfa($logger, ['totp'])->get(route('dashboard'));

        session()->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->subMinutes(2)->timestamp);
        $previousValue = session(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT);

        $response = $this->actingAsWithMfa($logger, ['totp'])->postJson(route('session.activity'));

        $response->assertOk();
        $response->assertJsonPath('protected', true);
        $this->assertGreaterThan($previousValue, session(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT));
    }

    public function test_protected_session_is_closed_server_side_when_timeout_is_exceeded(): void
    {
        $admin = $this->setUpAdminWithMfa(User::factory()->admin()->create());

        $this->actingAsWithMfa($admin, ['totp', 'webauthn'])->get(route('dashboard'));

        session()->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->subSeconds(301)->timestamp);

        $response = $this->actingAsWithMfa($admin, ['totp', 'webauthn'])->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Tu sesion se cerro por inactividad.');
        $this->assertGuest();
    }
}
