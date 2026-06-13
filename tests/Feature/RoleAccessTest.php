<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
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
     * Verifica que un cliente pueda acceder a su área privada.
     *
     * El rol 'cliente' solo requiere contraseña (sin MFA). Al autenticarse
     * debe poder ver el contenido del área de cliente sin restricciones.
     */
    public function test_client_can_access_client_home(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->actingAs($client)->get(route('client.home'));

        $response->assertOk();
        $response->assertSee('Area de cliente');
    }

    /**
     * Verifica que un cliente NO pueda acceder al dashboard general.
     *
     * El middleware de roles (EnsureUserHasRole) debe denegar el acceso
     * con código 403 (Forbidden) cuando un cliente intenta acceder a
     * rutas restringidas para usuario, administrador o logger.
     */
    public function test_client_cannot_access_dashboard(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    /**
     * Verifica que un usuario vea SOLO el bloque de trabajo general.
     *
     * El rol 'usuario' requiere TOTP. Una vez autenticado y con TOTP
     * superado, debe ver el bloque "Centro de trabajo general" pero
     * NO debe ver "Zona de control administrativo" ni "Logs de autenticacion".
     */
    public function test_user_sees_user_dashboard_block_only(): void
    {
        $user = $this->setUpTotpUser(User::factory()->user()->create());

        $this->actingAs($user);
        session(['factors_passed' => ['totp']]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Centro de trabajo general');
        $response->assertDontSee('Zona de control administrativo');
        $response->assertDontSee('Logs de autenticacion');
    }

    /**
     * Verifica que un administrador vea SOLO el bloque administrativo.
     *
     * El rol 'administrador' requiere TOTP + WebAuthn. Una vez autenticado
     * y con ambos factores superados, debe ver "Zona de control administrativo"
     * pero NO "Centro de trabajo general" ni "Logs de autenticacion".
     */
    public function test_admin_sees_admin_dashboard_block_only(): void
    {
        $admin = $this->setUpAdminWithMfa(User::factory()->admin()->create());

        $this->actingAs($admin);
        session(['factors_passed' => ['totp', 'webauthn']]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Zona de control administrativo');
        $response->assertDontSee('Centro de trabajo general');
        $response->assertDontSee('Logs de autenticacion');
    }

    /**
     * Verifica que un logger vea SOLO la tabla de logs de autenticación.
     *
     * El rol 'logger' requiere TOTP. Debe ver "Logs de autenticacion"
     * y la mención al "canal dedicado `auth`", pero NO debe ver
     * "Zona de control administrativo".
     */
    public function test_logger_sees_authentication_logs_table(): void
    {
        $logger = $this->setUpTotpUser(User::factory()->logger()->create());

        $this->actingAs($logger);
        session(['factors_passed' => ['totp']]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Logs de autenticacion');
        $response->assertSee('canal dedicado `auth`');
        $response->assertDontSee('Zona de control administrativo');
    }
}
