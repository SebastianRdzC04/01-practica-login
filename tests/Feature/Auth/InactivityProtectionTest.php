<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\InactivityProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivityProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Configura un usuario con autenticación TOTP habilitada.
     *
     * Encripta una clave secreta TOTP de 32 caracteres y marca al usuario
     * como teniendo el segundo factor habilitado. Esto evita que el middleware
     * EnsureTwoFactorConfigured redirija a la pantalla de configuración TOTP.
     *
     * @param  User  $user  Usuario al que se le habilitará TOTP.
     * @return User  El mismo usuario con TOTP configurado y persistido en BD.
     */
    protected function setUpTotpUser(User $user): User
    {
        $user->two_factor_secret = encrypt(str_repeat('A', 32));
        $user->two_factor_enabled = true;
        $user->save();

        return $user;
    }

    /**
     * Configura un usuario administrador con TOTP + WebAuthn.
     *
     * Además de habilitar TOTP como setUpTotpUser(), crea una credencial
     * WebAuthn dummy asociada al usuario. El rol administrador requiere
     * ambos factores ('totp', 'webauthn'), y el middleware EnsureTwoFactorConfigured
     * redirige a la configuración WebAuthn si el usuario no tiene ninguna
     * credencial registrada, incluso si factors_passed ya incluye 'webauthn'.
     *
     * @param  User  $user  Usuario administrador.
     * @return User  El mismo usuario con TOTP + WebAuthn configurados.
     */
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

    /**
     * Autentica al usuario y marca los factores MFA como superados en sesión.
     *
     * Establece en sesión la lista de factores que el usuario ya ha superado
     * (ej. ['totp'] o ['totp', 'webauthn']) para que el middleware
     * EnsureTwoFactorConfigured no redirija a las pantallas de verificación.
     * Luego autentica al usuario mediante actingAs().
     *
     * @param  User    $user    Usuario a autenticar.
     * @param  array   $factors Factores MFA ya superados (por defecto solo TOTP).
     * @return static
     */
    protected function actingAsWithMfa(User $user, array $factors = ['totp']): static
    {
        session(['factors_passed' => $factors]);

        return $this->actingAs($user);
    }

    /**
     * Verifica que la sesión de un administrador se marque como protegida
     * contra inactividad, con los tiempos de espera correctos.
     *
     * El middleware ProtectSessionFromInactivity debe establecer en sesión
     * las claves SESSION_KEY_PROTECTED, SESSION_KEY_MODAL_TIMEOUT_SECONDS,
     * SESSION_KEY_WARNING_TIMEOUT_SECONDS y SESSION_KEY_SERVER_TIMEOUT_SECONDS
     * con los valores configurados para el rol administrador.
     */
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

    /**
     * Verifica que la sesión de un cliente NO se marque como protegida.
     *
     * El rol cliente no requiere protección por inactividad, por lo que
     * la clave SESSION_KEY_PROTECTED no debe existir en sesión después
     * de acceder al área de cliente.
     */
    public function test_client_session_is_not_marked_as_inactivity_protected(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.home'))
            ->assertOk();

        $this->assertFalse(session()->has(InactivityProtection::SESSION_KEY_PROTECTED));
    }

    /**
     * Verifica que el heartbeat de una sesión protegida renueve
     * la marca de última actividad.
     *
     * Configura un usuario logger con TOTP, accede al dashboard para
     * activar la protección por inactividad, luego retrocede manualmente
     * el valor de SESSION_KEY_LAST_ACTIVITY_AT en 2 minutos. Realiza una
     * petición POST a la ruta de actividad (session.activity) y comprueba
     * que el nuevo timestamp sea mayor al anterior, confirmando que
     * el heartbeat refresca correctamente la sesión.
     */
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

    /**
     * Verifica que la sesión se cierre del lado del servidor cuando
     * se supera el tiempo máximo de inactividad.
     *
     * Configura un administrador con TOTP + WebAuthn, accede al dashboard,
     * luego retrocede SESSION_KEY_LAST_ACTIVITY_AT más allá del
     * límite configurado (301 segundos). Al realizar una nueva petición
     * al dashboard, comprueba que sea redirigido al login con el mensaje
     * de inactividad y que el usuario quede como invitado.
     */
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
