# Documentación de Funciones — Sistema de Autenticación Multifactor

## Módulo: Controladores de Autenticación

### `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

#### `create()`
**Descripción:** Display the login view.
**Parámetros:** Ninguno.
**Retorna:** `View` — Vista con el formulario de inicio de sesión.

#### `store(LoginRequest $request)`
**Descripción:** Procesa una solicitud de inicio de sesión.
**Parámetros:** `LoginRequest $request` — Solicitud validada que contiene las credenciales del usuario.
**Retorna:** `RedirectResponse` — Redirección al flujo de autenticación multifactor (MFA), área de cliente o panel de administración según corresponda.
**Excepciones:** `ValidationException` — Si las credenciales son incorrectas o la cuenta está configurada únicamente para acceso mediante Google.

#### `destroy(Request $request)`
**Descripción:** Log the user out of the application.
**Parámetros:** `Request $request` — The current HTTP request.
**Retorna:** `RedirectResponse` — Redirect to the home page.

---

### `app/Http/Controllers/Auth/RegisteredUserController.php`

#### `create()`
**Descripción:** Muestra la vista de registro de nuevos usuarios.
**Parámetros:** Ninguno.
**Retorna:** `View` — Vista con el formulario de registro.

#### `store(Request $request)`
**Descripción:** Procesa una solicitud de registro entrante.
**Parámetros:** `Request $request` — Solicitud HTTP con los datos del registro.
**Retorna:** `RedirectResponse` — Redirección a la ruta de inicio del usuario.
**Excepciones:** `ValidationException` — Si falla la validación o reCAPTCHA.

#### `ensureIsNotRateLimited(Request $request)` (protected)
**Descripción:** Verifica si la solicitud de registro ha excedido el límite permitido de intentos.
**Parámetros:** `Request $request` — Solicitud HTTP que contiene los datos del registro.
**Retorna:** `void`
**Excepciones:** `ValidationException` — Si se supera el límite de intentos permitidos.

#### `throttleKey(Request $request)` (protected)
**Descripción:** Genera la clave única para limitar la tasa de solicitudes de registro.
**Parámetros:** `Request $request` — Solicitud HTTP con los datos del registro.
**Retorna:** `string` — Clave única en formato 'register:email|ip'.

---

### `app/Http/Controllers/Auth/GoogleLoginController.php`

#### `redirect(Request $request)`
**Descripción:** Redirige al usuario a Google OAuth para autenticación.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `RedirectResponse` — Redirección a Google OAuth.

#### `callback(Request $request)`
**Descripción:** Procesa el callback de Google OAuth después de la autenticación.
**Parámetros:** `Request $request` — Solicitud HTTP con el código de autorización.
**Retorna:** `RedirectResponse` — Redirección a la ruta de inicio o a MFA.

---

### `app/Http/Controllers/TwoFactorController.php`

#### `showSetup(Request $request)`
**Descripción:** Muestra la página de configuración de TOTP (Google Authenticator).
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `View` — Vista con el código QR y la clave secreta TOTP.

#### `confirmSetup(Request $request)`
**Descripción:** Confirma y guarda la configuración TOTP del usuario.
**Parámetros:** `Request $request` — Solicitud HTTP con el código TOTP y reCAPTCHA.
**Retorna:** `RedirectResponse|View` — Redirección a WebAuthn, inicio, o error.
**Excepciones:** `ValidationException` — Si falla la verificación de reCAPTCHA.

#### `showVerify(Request $request)`
**Descripción:** Muestra la página de verificación TOTP.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `View` — Vista del formulario de verificación TOTP.

#### `verify(Request $request)`
**Descripción:** Verifica el código TOTP ingresado por el usuario.
**Parámetros:** `Request $request` — Solicitud HTTP con el código TOTP y reCAPTCHA.
**Retorna:** `RedirectResponse|View` — Redirección a inicio, MFA, o error.
**Excepciones:** `ValidationException` — Si falla reCAPTCHA o límite de tasa.

---

### `app/Http/Controllers/WebAuthnController.php`

#### `ensureTotpPassed(Request $request)` (protected)
**Descripción:** Verifica que el usuario haya superado TOTP antes de WebAuthn.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `mixed|null|RedirectResponse` — Null si TOTP está superado, RedirectResponse si debe verificar TOTP primero.

#### `showSetup(Request $request)`
**Descripción:** Muestra la página de configuración WebAuthn.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `RedirectResponse|View` — Redirección a TOTP o vista de configuración.

#### `options(AttestationRequest $request)`
**Descripción:** Genera las opciones de registro WebAuthn (attestation).
**Parámetros:** `AttestationRequest $request` — Solicitud de attestation WebAuthn.
**Retorna:** `JsonResponse` — Respuesta JSON con las opciones de registro.

#### `register(AttestedRequest $request)`
**Descripción:** Guarda una nueva credencial WebAuthn registrada por el usuario.
**Parámetros:** `AttestedRequest $request` — Solicitud con la credencial atestiguada.
**Retorna:** `JsonResponse` — Respuesta JSON indicando éxito o error.
**Excepciones:** `\Exception` — Si falla el registro de la credencial.

#### `showAuthenticate(Request $request)`
**Descripción:** Muestra la página de autenticación WebAuthn.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `RedirectResponse|View` — Redirección a TOTP o vista de autenticación.

#### `assertionOptions(AssertionRequest $request)`
**Descripción:** Genera las opciones de autenticación WebAuthn (assertion).
**Parámetros:** `AssertionRequest $request` — Solicitud de assertion WebAuthn.
**Retorna:** `JsonResponse` — Respuesta JSON con las opciones de autenticación.

#### `authenticate(AssertedRequest $request)`
**Descripción:** Autentica al usuario mediante una credencial WebAuthn.
**Parámetros:** `AssertedRequest $request` — Solicitud con la aserción WebAuthn.
**Retorna:** `JsonResponse` — Respuesta JSON (200 éxito, 422 error, 429 límite).

#### `detectDeviceAlias(Request $request)` (protected)
**Descripción:** Detecta el alias del dispositivo basado en el user agent.
**Parámetros:** `Request $request` — Solicitud HTTP con el user agent.
**Retorna:** `string` — Alias descriptivo del dispositivo biométrico.

---

## Módulo: Controladores de Dashboard y Perfil

### `app/Http/Controllers/DashboardController.php`

#### `__invoke(Request $request)`
**Descripción:** Muestra el dashboard del usuario autenticado.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `View` — Vista del dashboard con los datos del usuario.

---

### `app/Http/Controllers/ClientAreaController.php`

#### `__invoke(Request $request)`
**Descripción:** Muestra el área de cliente del usuario autenticado.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `View` — Vista del área de cliente con los datos del usuario.

---

### `app/Http/Controllers/ProfileController.php`

#### `edit(Request $request)`
**Descripción:** Muestra el formulario de edición del perfil del usuario.
**Parámetros:** `Request $request` — Solicitud HTTP entrante.
**Retorna:** `View` — Vista del formulario de edición de perfil.

#### `update(ProfileUpdateRequest $request)`
**Descripción:** Actualiza la información del perfil del usuario.
**Parámetros:** `ProfileUpdateRequest $request` — Solicitud con los datos validados.
**Retorna:** `RedirectResponse` — Redirección a la edición de perfil.
**Excepciones:** `ValidationException` — Si falla la verificación de reCAPTCHA.

#### `destroy(Request $request)`
**Descripción:** Elimina la cuenta del usuario autenticado.
**Parámetros:** `Request $request` — Solicitud HTTP con la contraseña de confirmación.
**Retorna:** `RedirectResponse` — Redirección a la página principal.
**Excepciones:** `ValidationException` — Si falla validación de contraseña o reCAPTCHA.

---

## Módulo: Middleware de Seguridad

### `app/Http/Middleware/SecurityHeadersMiddleware.php`

#### `handle(Request $request, Closure $next)`
**Descripción:** Agrega cabeceras de seguridad HTTP a todas las respuestas salientes.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware.
**Retorna:** `\Illuminate\Http\Response` — Respuesta HTTP con las cabeceras de seguridad añadidas.

---

### `app/Http/Middleware/LogRouteVisit.php`

#### `handle(Request $request, Closure $next)`
**Descripción:** Registra en el log de autenticación cada visita exitosa a una ruta protegida.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware.
**Retorna:** `Response` — Respuesta HTTP generada por la aplicación, sin modificaciones.

---

### `app/Http/Middleware/EnsureUserHasRole.php`

#### `handle(Request $request, Closure $next, string ...$roles)`
**Descripción:** Verifica que el usuario autenticado posea al menos uno de los roles especificados.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware. | `string ...$roles` — Lista de nombres de roles permitidos para acceder a la ruta.
**Retorna:** `Response` — Respuesta HTTP del siguiente middleware si el usuario tiene el rol.
**Excepciones:** `HttpException` (403) — Cuando el usuario no posee ninguno de los roles requeridos.

---

### `app/Http/Middleware/ProtectSessionFromInactivity.php`

#### `handle(Request $request, Closure $next, ?string $mode = null)`
**Descripción:** Protege la sesión del usuario cerrándola automáticamente tras un período de inactividad.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware. | `string|null $mode` — Modo de operación: 'force' para forzar la protección independientemente de la configuración del usuario.
**Retorna:** `Response` — Redirección al login si la sesión expiró, o respuesta del siguiente middleware si la sesión sigue activa.

#### `clearState(Request $request)` (private)
**Descripción:** Elimina todos los datos de protección por inactividad almacenados en la sesión.
**Parámetros:** `Request $request` — La solicitud HTTP de la cual se limpiará el estado de sesión.
**Retorna:** `void`

---

## Módulo: Middleware de Autenticación y MFA

### `app/Http/Middleware/EnsureTwoFactorConfigured.php`

#### `handle(Request $request, Closure $next)`
**Descripción:** Verifica que el usuario autenticado tenga configurados los factores de autenticación multifactor requeridos.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware.
**Retorna:** `RedirectResponse|mixed` — Redirección a configuración/verificación MFA o continúa con el siguiente middleware.

---

### `app/Http/Middleware/EnsurePendingAuth.php`

#### `handle(Request $request, Closure $next)`
**Descripción:** Verifica si existe una sesión de autenticación pendiente y completa el inicio de sesión.
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware.
**Retorna:** `RedirectResponse|mixed` — Redirección al login o continúa con la solicitud.

---

### `app/Http/Middleware/RedirectIfAuthenticated.php`

#### `handle(Request $request, Closure $next, string ...$guards)`
**Descripción:** Redirige a los usuarios autenticados lejos de rutas públicas (login, registro, etc.).
**Parámetros:** `Request $request` — La solicitud HTTP entrante. | `Closure $next` — Función que delega el procesamiento al siguiente middleware. | `string ...$guards` — Lista de guards de autenticación a verificar (opcional).
**Retorna:** `Response` — Redirección al dashboard del usuario si está autenticado, o respuesta del siguiente middleware si es invitado.

---

## Módulo: Modelos

### `app/Models/User.php`

#### `booted()` (protected static)
**Descripción:** Inicializa eventos del modelo Eloquent.
**Parámetros:** Ninguno.
**Retorna:** `void`

#### `hasRole(string ...$roles)`
**Descripción:** Determina si el usuario posee alguno de los roles especificados.
**Parámetros:** `string ...$roles` — Lista de roles a verificar.
**Retorna:** `bool` — True si el rol del usuario coincide con alguno de los roles dados.

#### `homeRouteName()`
**Descripción:** Obtiene el nombre de la ruta de inicio según el rol del usuario.
**Parámetros:** Ninguno.
**Retorna:** `string` — Nombre de la ruta de inicio.

#### `requiredFactors()`
**Descripción:** Devuelve los factores de autenticación requeridos según el rol.
**Parámetros:** Ninguno.
**Retorna:** `array<int, string>` — Arreglo con los factores de autenticación requeridos.

#### `hasWebauthnEnabled()`
**Descripción:** Verifica si el usuario tiene credenciales WebAuthn registradas.
**Parámetros:** Ninguno.
**Retorna:** `bool` — True si existe al menos una credencial WebAuthn.

---

## Módulo: Soporte (Support)

### `app/Support/AuthLog.php`

#### `info(string $message, array $context = [])` (static)
**Descripción:** Registra un mensaje informativo en el canal de autenticación.
**Parámetros:** `string $message` — Mensaje descriptivo del evento. | `array<string, mixed> $context` — Contexto adicional del evento.
**Retorna:** `void`

#### `warning(string $message, array $context = [])` (static)
**Descripción:** Registra un mensaje de advertencia en el canal de autenticación.
**Parámetros:** `string $message` — Mensaje descriptivo de la advertencia. | `array<string, mixed> $context` — Contexto adicional del evento.
**Retorna:** `void`

#### `error(string $message, array $context = [])` (static)
**Descripción:** Registra un mensaje de error en el canal de autenticación.
**Parámetros:** `string $message` — Mensaje descriptivo del error. | `array<string, mixed> $context` — Contexto adicional del evento.
**Retorna:** `void`

#### `debug(string $message, array $context = [])` (static)
**Descripción:** Registra un mensaje de depuración en el canal de autenticación.
**Parámetros:** `string $message` — Mensaje descriptivo para depuración. | `array<string, mixed> $context` — Contexto adicional del evento.
**Retorna:** `void`

#### `normalizeContext(array $context)` (private static)
**Descripción:** Normaliza el contexto agregando el entorno de aplicación.
**Parámetros:** `array<string, mixed> $context` — Contexto original del evento.
**Retorna:** `array<string, mixed>` — Contexto enriquecido con app_env.

---

### `app/Support/LoginLockout.php`

#### `throttleKey(string $email, string $ipAddress)` (static)
**Descripción:** Genera la clave de estrangulamiento para el rate limiter.
**Parámetros:** `string $email` — Correo electrónico del usuario. | `string $ipAddress` — Dirección IP del solicitante.
**Retorna:** `string` — Clave única de estrangulamiento.

#### `isLocked(string $email, string $ipAddress)` (static)
**Descripción:** Verifica si se ha superado el límite de intentos permitidos.
**Parámetros:** `string $email` — Correo electrónico del usuario. | `string $ipAddress` — Dirección IP del solicitante.
**Retorna:** `bool` — True si el origen está bloqueado por exceso de intentos.

#### `secondsRemaining(string $email, string $ipAddress)` (static)
**Descripción:** Obtiene los segundos restantes hasta que se libere el bloqueo.
**Parámetros:** `string $email` — Correo electrónico del usuario. | `string $ipAddress` — Dirección IP del solicitante.
**Retorna:** `int` — Número de segundos restantes de bloqueo.

#### `state(string $email, string $ipAddress)` (static)
**Descripción:** Obtiene el estado completo del bloqueo de autenticación.
**Parámetros:** `string $email` — Correo electrónico del usuario. | `string $ipAddress` — Dirección IP del solicitante.
**Retorna:** `array{locked: bool, seconds_remaining: int, max_attempts: int}` — Estado del bloqueo.

---

### `app/Support/InactivityProtection.php`

#### `configFor(?User $user)` (static)
**Descripción:** Obtiene la configuración de inactividad para un usuario dado.
**Parámetros:** `\App\Models\User|null $user` — Usuario autenticado o null si es invitado.
**Retorna:** `array{enabled: bool, modal_timeout_seconds: int, warning_timeout_seconds: int, server_timeout_seconds: int}` — Configuración de inactividad.

---

## Módulo: Form Requests

### `app/Http/Requests/Auth/LoginRequest.php`

#### `authorize()`
**Descripción:** Determina si el usuario está autorizado a realizar esta solicitud.
**Parámetros:** Ninguno.
**Retorna:** `bool` — Siempre retorna true.

#### `rules()`
**Descripción:** Obtiene las reglas de validación para la solicitud de inicio de sesión.
**Parámetros:** Ninguno.
**Retorna:** `array<string, Rule|array|string>` — Reglas de validación por campo.

#### `authenticate()`
**Descripción:** Intenta autenticar las credenciales de la solicitud.
**Parámetros:** Ninguno.
**Retorna:** `void`
**Excepciones:** `ValidationException` — Cuando las credenciales son inválidas o el rate limiter está activo.

#### `ensureIsNotRateLimited()`
**Descripción:** Verifica que la solicitud no supere el límite de intentos.
**Parámetros:** Ninguno.
**Retorna:** `void`
**Excepciones:** `ValidationException` — Cuando se ha superado el límite de intentos.

#### `throttleKey()`
**Descripción:** Obtiene la clave de estrangulamiento para el rate limiter.
**Parámetros:** Ninguno.
**Retorna:** `string` — Clave única de estrangulamiento.

---



## Módulo: Tests

### `tests/Feature/Auth/AuthenticationTest.php`

#### `test_login_screen_can_be_rendered()`
**Descripción:** Verifica que la pantalla de inicio de sesión se renderice correctamente.

#### `test_users_can_authenticate_using_the_login_screen()`
**Descripción:** Verifica que un usuario pueda autenticarse correctamente mediante el formulario de inicio de sesión.

#### `test_users_can_not_authenticate_with_invalid_password()`
**Descripción:** Verifica que un usuario no pueda autenticarse con una contraseña incorrecta.

#### `test_users_can_logout()`
**Descripción:** Verifica que un usuario autenticado pueda cerrar sesión correctamente.

#### `test_clients_are_redirected_to_their_private_area_after_login()`
**Descripción:** Verifica que los clientes sean redirigidos a su área privada tras el login.

---

### `tests/Feature/Auth/InactivityProtectionTest.php`

#### `setUpTotpUser(User $user)` (protected)
**Descripción:** Configura un usuario con autenticación TOTP habilitada.
**Parámetros:** `User $user` — Usuario al que se le habilitará TOTP.
**Retorna:** `User` — El mismo usuario con TOTP configurado y persistido en BD.

#### `setUpAdminWithMfa(User $user)` (protected)
**Descripción:** Configura un usuario administrador con TOTP + WebAuthn.
**Parámetros:** `User $user` — Usuario administrador.
**Retorna:** `User` — El mismo usuario con TOTP + WebAuthn configurados.

#### `actingAsWithMfa(User $user, array $factors = ['totp'])` (protected)
**Descripción:** Autentica al usuario y marca los factores MFA como superados en sesión.
**Parámetros:** `User $user` — Usuario a autenticar. | `array $factors` — Factores MFA ya superados (por defecto solo TOTP).
**Retorna:** `static`

#### `test_admin_session_is_marked_as_inactivity_protected()`
**Descripción:** Verifica que la sesión de un administrador se marque como protegida contra inactividad, con los tiempos de espera correctos.

#### `test_client_session_is_not_marked_as_inactivity_protected()`
**Descripción:** Verifica que la sesión de un cliente NO se marque como protegida.

#### `test_protected_session_heartbeat_refreshes_last_activity()`
**Descripción:** Verifica que el heartbeat de una sesión protegida renueve la marca de última actividad.

#### `test_protected_session_is_closed_server_side_when_timeout_is_exceeded()`
**Descripción:** Verifica que la sesión se cierre del lado del servidor cuando se supera el tiempo máximo de inactividad.

---

### `tests/Feature/RoleAccessTest.php`

#### `setUpTotpUser(User $user)` (protected)
**Descripción:** Configura un usuario con autenticación TOTP habilitada.
**Parámetros:** `User $user` — Usuario al que se le habilitará TOTP.
**Retorna:** `User` — El mismo usuario con TOTP configurado y persistido en BD.

#### `setUpAdminWithMfa(User $user)` (protected)
**Descripción:** Configura un usuario administrador con TOTP + WebAuthn.
**Parámetros:** `User $user` — Usuario administrador.
**Retorna:** `User` — El mismo usuario con TOTP + WebAuthn configurados.

#### `test_client_can_access_client_home()`
**Descripción:** Verifica que un cliente pueda acceder a su área privada.

#### `test_client_cannot_access_dashboard()`
**Descripción:** Verifica que un cliente NO pueda acceder al dashboard general.

#### `test_user_sees_user_dashboard_block_only()`
**Descripción:** Verifica que un usuario vea SOLO el bloque de trabajo general.

#### `test_admin_sees_admin_dashboard_block_only()`
**Descripción:** Verifica que un administrador vea SOLO el bloque administrativo.

#### `test_logger_sees_authentication_logs_table()`
**Descripción:** Verifica que un logger vea SOLO la tabla de logs de autenticación.

---

### `tests/Concerns/InteractsWithAuthMongoLogs.php`

#### `clearAuthMongoLogs()` (protected)
**Descripción:** Elimina todos los registros de la tabla auth_logs.
**Parámetros:** Ninguno.
**Retorna:** `void`

#### `assertAuthMongoLogExists(array $criteria)` (protected)
**Descripción:** Verifica que exista al menos un registro en auth_logs que coincida con los criterios proporcionados.
**Parámetros:** `array $criteria` — Criterios de búsqueda (campos y valores).
**Retorna:** `void`

#### `authMongoLogs()` (protected)
**Descripción:** Obtiene todos los registros de la tabla auth_logs.
**Parámetros:** Ninguno.
**Retorna:** `array` — Arreglo de registros.
