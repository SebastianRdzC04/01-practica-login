# Practica Login - Contexto y Recomendaciones

## Arquitectura Actual

### Stack Tecnologico
- **Backend:** Laravel 10 (PHP 8.x)
- **Frontend:** Blade + Alpine.js + Vite
- **Base de datos:** MySQL 8.4 (principal + logs de auditoria)
- **Despliegue:** DigitalOcean (LB + 2 APPs + MySQL) con Nginx, Cloudflare
- **HTTPS:** Cloudflare Origin Certificate, TLS 1.2/1.3 con post-cuantico (X25519MLKEM768)

### Roles de Usuario
| Rol | MFA Requerido | Home |
|-----|--------------|------|
| `cliente` | Solo password | `/cliente` |
| `usuario` | password + TOTP | `/dashboard` |
| `administrador` | password + TOTP + WebAuthn | `/dashboard` |
| `logger` | password + TOTP | `/dashboard` |

### Sistema de Autenticacion (Deferred Login)
El login usa un sistema **diferido** (no se autentica al usuario hasta que completa todos los factores):

1. **Password** → se valida, se guarda `pending_auth_user_id` en sesion
2. **Factores pendientes** → se redirige secuencialmente a cada factor no configurado o no verificado
3. **Completado** → cuando todos los factores requeridos estan en `factors_passed`, se llama `Auth::loginUsingId()`

### Factores de Autenticacion
- **Password** — validacion con Hash::check
- **Google OAuth** — via Laravel Socialite (reemplaza password para usuarios google-only)
- **TOTP** — Google2FA + QR code via BaconQrCode
- **WebAuthn** — Laragear/WebAuthn (biometrico: huella, Face ID, Windows Hello)

### Middleware Stack
```
web (general) → pending.auth (MFA diferido) / auth (autenticado)
  → auth.session → log.route.visit → inactivity.protected → ensure.mfa
  → role:cliente|usuario|administrador|logger
```

### Seguridad Implementada
- **Rate Limiting:** Login (5 intentos), TOTP (3 intentos), WebAuthn (3 intentos)
- **reCAPTCHA:** En login, registro, TOTP setup/verify, cambio password, perfil
- **Inactividad:** Cierre automatico de sesion por inactividad (configurable por rol)
- **CSP Headers:** SecurityHeadersMiddleware con Content-Security-Policy
- **CSRF Protection:** En todos los formularios + headers X-CSRF-TOKEN en AJAX
- **Validacion de origen:** EnsurePendingAuth protege rutas MFA de accesos directos

### Logging (MariaDB)
Tabla `auth_logs` en MariaDB registra eventos de:
- Login/logout, registro de usuarios, Google OAuth
- TOTP setup/verify, WebAuthn setup/authenticate
- Cambios de password, confirmaciones, reseteos
- Verificacion de email
- Accesos no autorizados (403)
- Cierre por inactividad
- Dashboard y area de cliente visitados

### Sesion
- Driver: `file` (storage/framework/sessions)
- Lifetime: 120 minutos
- Proteccion por inactividad con timeout configurable por rol

---

## Cambios Realizados Recientemente

### Bugfixes
1. **WebAuthn RP ID** — Los navegadores rechazan IPs como RP ID. Se usa el dominio del request.
2. **Rate limiting en MFA** — Se agregaron limites de 3 intentos para TOTP verify y WebAuthn authenticate con RateLimiter de Laravel.

### Features
1. **Perfil solo para clientes** — Rutas de perfil protegidas con middleware `role:cliente`, enlace oculto para otros roles.
2. **Seguridad en navegacion** — Solo clientes ven opciones de configurar TOTP/WebAuthn en el menu.
3. **Logging mejora** — Dashboard y ClientArea subidos a nivel `info` para persistir en MariaDB.

---

## Recomendaciones

### Corto Plazo (Prioritario)

#### 1. Forzar HTTPS en produccion
- Actualmente se accede por HTTP (puerto 8080). WebAuthn requiere contexto seguro.
- `http://localhost` es considerado seguro por los navegadores, pero en produccion se necesita HTTPS real.
- Agregar redireccion HTTP → HTTPS en el middleware o Nginx.

#### 2. Renovar TOTP: invalidar secret anterior
- Cuando un cliente renueva TOTP desde el menu, el secret anterior deberia invalidarse.
- Actualmente `confirmSetup` sobreescribe `two_factor_secret` y guarda `two_factor_enabled=true`.
- Verificar que no haya un periodo donde dos tokens sean validos.

#### 3. Sesion: migrar a Redis/Memcached en produccion
- `SESSION_DRIVER=database` permite escalar horizontalmente con upstream de Nginx.
- Redis ofrece persistencia, cache distribuida y sesiones compartidas entre contenedores (opcional).

#### 4. Prueba de recuperacion de acceso
- Si un usuario pierde el acceso a TOTP y WebAuthn, no hay forma de recuperar la cuenta.
- Implementar codigos de respaldo (backup codes) o recovery email.

### Mediano Plazo

#### 5. Panel de administracion de usuarios
- CRUD de usuarios con asignacion de roles.
- Logs de auditoria de cambios (quien creo/modifico/elimino que usuario).
- Dashboard con metricas de seguridad: intentos fallidos, usuarios activos, etc.

#### 6. Notificaciones por email
- Alertas de login desde ubicaciones nuevas.
- Notificacion cuando se configura/renueva MFA.
- Confirmacion de cambio de password.

#### 7. Mejoras en logging
- Agregar `user_agent` completo parseado (OS, browser, device) en los logs.
- Indices en MariaDB para consultas frecuentes: `event`, `created_at`, `user_id`.
- Rotacion/retention de logs en MariaDB (purga programada).

#### 8. WebAuthn multi-dispositivo
- Permitir registrar multiples dispositivos biometricos.
- Gestion de dispositivos desde el perfil (ver, renombrar, eliminar).

#### 9. CSRF + API stateless
- Si se implementa API, separar autenticacion web (session) de API (tokens Sanctum).
- Las rutas MFA actuales dependen de CSRF; para API usaria tokens.

### Largo Plazo / Opcional

#### 10. 2FA con authenticator app (TOTP via notificacion push)
- Ademas del codigo manual, implementar push notification via Firebase.

#### 11. Passkeys (WebAuthn sin password)
- Registro de passkey durante el registro inicial (passwordless).
- Login con solo biometrico para clientes que lo prefieran.

#### 12. Registro de dispositivos confiables
- Recordar dispositivo por N dias para no pedir MFA en cada login.
- Cookie firmada con token de dispositivo.

#### 13. Hardening adicional
- `Strict-Transport-Security` header (HSTS).
- Rate limiting global por IP (no solo por endpoint).
- Logout de todos los dispositivos (forzar renovacion de sesion).
- Deteccion de fuerza bruta distribuida (multiple IPs, misma cuenta).

#### 14. Tests automatizados
- Tests de feature para el flujo completo de login diferido.
- Tests de rate limiting.
- Tests de autorizacion por roles.
- Tests de WebAuthn simulados.

#### 15. Frontend moderno
- Migrar a Livewire, React o Vue para mejor UX en tiempo real.
- SPA con token-based auth y refresh tokens.

---

## Estructura de Archivos Clave

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthenticatedSessionController.php   # Login con factores diferidos
│   │   │   ├── GoogleLoginController.php            # OAuth Google
│   │   │   ├── RegisteredUserController.php         # Registro
│   │   │   ├── PasswordController.php               # Cambio de password
│   │   │   ├── ConfirmablePasswordController.php    # Confirmacion de password
│   │   │   ├── VerifyEmailController.php            # Verificacion email
│   │   │   ├── NewPasswordController.php            # Reset password
│   │   │   ├── EmailVerificationPromptController.php
│   │   │   └── SessionActivityController.php        # Heartbeat de sesion
│   │   ├── TwoFactorController.php                  # TOTP setup & verify
│   │   ├── WebAuthnController.php                   # WebAuthn register & auth
│   │   ├── DashboardController.php                  # Home admin/user/logger
│   │   ├── ClientAreaController.php                 # Home cliente
│   │   └── ProfileController.php                    # Perfil (solo cliente)
│   ├── Middleware/
│   │   ├── EnsurePendingAuth.php                    # Auth diferida
│   │   ├── EnsureTwoFactorConfigured.php            # Verifica factores MFA
│   │   ├── EnsureUserHasRole.php                    # Autorizacion por rol
│   │   ├── ProtectSessionFromInactivity.php         # Timeout de sesion
│   │   ├── RedirectIfAuthenticated.php              # Guest redirect
│   │   ├── LogRouteVisit.php                        # Logging de visitas
│   │   └── SecurityHeadersMiddleware.php            # CSP headers
│   └── Requests/
│       └── Auth/LoginRequest.php                    # Validacion + rate limit login
├── Models/User.php                                  # Roles, factores, home route
├── Services/RecaptchaService.php                    # Verificacion reCAPTCHA
├── Support/
│   ├── AuthLog.php                                  # Constantes + escritura a DB
│   ├── InactivityProtection.php                     # Config inactividad por rol
│   └── LoginLockout.php                             # Config rate limit login

config/
├── webauthn.php                                     # RP ID, origins, challenge
├── logging.php                                      # Canales de log
└── services.php                                     # reCAPTCHA, Google OAuth

resources/views/
├── layouts/
│   ├── navigation.blade.php                         # Nav con role checks
│   └── guest.blade.php                              # Layout invitado
├── profile/                                         # Solo cliente
├── mfa/
│   ├── verify.blade.php                             # TOTP verify
│   ├── setup.blade.php                              # TOTP setup QR
│   ├── webauthn_auth.blade.php                      # WebAuthn authenticate
│   └── webauthn_setup.blade.php                     # WebAuthn register
├── dashboard.blade.php                              # Admin/user/logger
└── client/
    └── home.blade.php                               # Cliente

routes/
├── web.php                                          # Rutas auth + MFA
└── auth.php                                         # Login, registro, password
```
