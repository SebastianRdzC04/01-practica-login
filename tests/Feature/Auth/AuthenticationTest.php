<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que la pantalla de inicio de sesión se renderice correctamente.
     *
     * Realiza una petición GET a /login y comprueba que el servidor
     * responda con un código HTTP 200, garantizando que la vista de
     * login está accesible y funciona sin errores.
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Verifica que un usuario pueda autenticarse correctamente mediante
     * el formulario de inicio de sesión.
     *
     * Crea un usuario con rol 'client', envía credenciales válidas via POST
     * a /login y comprueba que la sesión se establezca (assertAuthenticated),
     * que sea redirigido a client.home, y que se registre un log de
     * 'login_success' en la colección de MongoDB.
     */
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->client()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);



        $this->assertAuthenticated();
        $response->assertRedirect(route('client.home'));

        $this->assertAuthMongoLogExists([
            'context.event' => 'login_success',
            'context.email' => $user->email,
            'context.succeeded' => true,
        ]);
    }

    /**
     * Verifica que un usuario no pueda autenticarse con una contraseña incorrecta.
     *
     * Envía credenciales inválidas via POST a /login, comprueba que
     * el usuario siga siendo invitado (assertGuest) y que se registre
     * un log de 'login_failed' en MongoDB con el email del usuario.
     */
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->client()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();

        $this->assertAuthMongoLogExists([
            'context.event' => 'login_failed',
            'context.email' => $user->email,
            'context.succeeded' => false,
        ]);
    }

    /**
     * Verifica que un usuario autenticado pueda cerrar sesión correctamente.
     *
     * Autentica al usuario, realiza una petición POST a /logout y
     * comprueba que la sesión se destruya (assertGuest), que sea
     * redirigido a / y que se registre un log de 'logout' en MongoDB.
     */
    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');

        $this->assertAuthMongoLogExists([
            'context.event' => 'logout',
            'context.email' => $user->email,
            'context.succeeded' => true,
        ]);
    }

    /**
     * Verifica que los clientes sean redirigidos a su área privada tras el login.
     *
     * Crea un usuario con rol 'client', se autentica via POST a /login
     * y comprueba que la respuesta redirija a la ruta client.home,
     * confirmando la redirección post-autenticación específica del rol.
     */
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
