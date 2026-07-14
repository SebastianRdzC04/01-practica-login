# Infraestructura de Despliegue

## Servidores

| Servidor | IP Publica | IP Privada | SSH Port | Rol |
|----------|------------|------------|----------|-----|
| Load Balancer | 143.110.237.225 | 10.124.0.5 | 59187 | Nginx 1.30.3, Cloudflare Origin Cert |
| APP1 | 164.90.155.81 | 10.124.0.3 | 59243 | Laravel + PHP-FPM + Nginx (puerto 52043) |
| APP2 | 24.199.118.31 | 10.124.0.2 | 59351 | Laravel + PHP-FPM + Nginx (puerto 53378) |
| MySQL | 165.232.154.223 | 10.124.0.4 | 59406 | MySQL 8.4.10 (puerto 53072) |

SO: Rocky Linux 9.x en todos | SELinux: Enforcing en todos

---

## Cadena de Certificados TLS

### CA Raiz (Certificate Authority)

Ubicada en el servidor **MySQL**:

```
/etc/pki/ca/
  private/
    ca.key.pem          # Clave privada de la CA raiz
    mysql.key.pem       # Clave del cert MySQL server
    app1.key.pem        # Clave del cert APP1
    app2.key.pem        # Clave del cert APP2
    jumphost.key.pem    # Clave del cert jumphost
  certs/
    ca.cert.pem         # Certificado de la CA raiz
    mysql.cert.pem      # Cert firmado para MySQL server
    mysql.csr           # CSR de MySQL
    app1.cert.pem       # Cert firmado para APP1
    app1.csr            # CSR de APP1
    app2.cert.pem       # Cert firmado para APP2
    app2.csr            # CSR de APP2
  newcerts/
    1000.pem - 1003.pem # Certificados generados
  openssl.cnf           # Config de la CA
  index.txt             # Registro de certs emitidos
  serial                # Numero serial actual
```

### Load Balancer (LB)

```
/etc/nginx/ssl/
  origin-cert.pem       # Certificado Cloudflare Origin (emitido por Cloudflare)
  origin-key.pem        # Clave privada del cert Cloudflare Origin
  ca-chain.pem          # CA chain para verificar certs de los backends (APP1/APP2)
```

- TLS entrante (CF → LB): Certificado Cloudflare Origin
- TLS saliente (LB → APPs): Verifica certs de APP1/APP2 usando `ca-chain.pem`
- Post-cuantico: `ssl_ecdh_curve X25519MLKEM768:P-256:P-384:X25519`

### APP1

```
/etc/pki/app1/ca/
  certs/
    app1.cert.pem       # Cert TLS para Nginx (server)
    ca.cert.pem         # CA raiz (copia)
  private/
    app1.key.pem        # Clave privada Nginx
    ca.key.pem          # CA raiz (copia)

/etc/pki/mysql-client/
  ca.cert.pem           # CA raiz para verificar cert MySQL server
  app1.cert.pem         # Cert cliente para conexion a MySQL (X509 auth)
  app1.key.pem          # Clave privada cliente MySQL
```

### APP2

```
/etc/pki/app2/ca/
  certs/
    app2.cert.pem       # Cert TLS para Nginx (server)
    ca.cert.pem         # CA raiz (copia)
  private/
    app2.key.pem        # Clave privada Nginx
    ca.key.pem          # CA raiz (copia)

/etc/pki/mysql-client/
  ca.cert.pem           # CA raiz para verificar cert MySQL server
  app2.cert.pem         # Cert cliente para conexion a MySQL (X509 auth)
  app2.key.pem          # Clave privada cliente MySQL
```

### MySQL Server

```
/etc/pki/ca/
  certs/
    ca.cert.pem         # CA raiz
    mysql.cert.pem      # Cert TLS del servidor MySQL
  private/
    mysql.key.pem       # Clave privada MySQL
```

---

## Flujo TLS Completo

```
Usuario → Cloudflare (HTTPS) → LB (puerto 443, cert CF Origin) → APP1/APP2 (puertos 52043/53378, cert self-signed CA) → MySQL (puerto 53072, cert self-signed CA)
```

Cada salto TLS es una conexion independiente con su propio certificado.

---

## Configuracion Nginx

### LB - `/etc/nginx/conf.d/loadbalance.conf`

- Puerto 80: Redirect 301 → HTTPS
- Puerto 443: SSL con cert Cloudflare Origin, HTTP/2
- Split 50/50 entre APP1 y APP2 (round-robin por `split_clients`)
- Rate limiting: 10 req/s por IP, max 20 conexiones por IP
- Proxy SSL a backends con verificacion de cert
- Error pages personalizadas: 502, 503, 504
- Headers: solo HSTS (los demas los maneja el middleware PHP)

### APP1 - `/etc/nginx/conf.d/app.conf`

- Puerto 52043 SSL con cert self-signed de CA
- Root: `/var/www/app/public`
- PHP-FPM via unix socket `/run/php-fpm/www.sock`
- Bloqueo de dotfiles

### APP2 - `/etc/nginx/conf.d/app.conf`

- Puerto 53378 SSL con cert self-signed de CA
- Misma configuracion que APP1

---

## MySQL - `/etc/my.cnf`

```
port = 53072
bind-address = 0.0.0.0
ssl_ca   = /etc/pki/ca/certs/ca.cert.pem
ssl_cert = /etc/pki/ca/certs/mysql.cert.pem
ssl_key  = /etc/pki/ca/private/mysql.key.pem
tls_version = TLSv1.2,TLSv1.3
require_secure_transport = ON
local_infile = 0
```

- Base de datos: `login_db`
- Usuarios: `logindatabase` (con REQUIRE X509) — uno por APP con su cert
- Puertos de admin: 53072 (no el default 3306)

---

## Configuracion Laravel (.env)

### APP1 (`/var/www/app/.env`)

| Variable | Valor |
|----------|-------|
| APP_ENV | production |
| APP_DEBUG | false |
| APP_URL | https://devas-projects.sbs |
| APP_SERVER_ID | 1 |
| DB_HOST | 10.124.0.4 |
| DB_PORT | 53072 |
| DB_DATABASE | login_db |
| DB_USERNAME | logindatabase |
| DB_SSL_CA | /etc/pki/mysql-client/ca.cert.pem |
| DB_SSL_CERT | /etc/pki/mysql-client/app1.cert.pem |
| DB_SSL_KEY | /etc/pki/mysql-client/app1.key.pem |
| SESSION_DRIVER | database |
| SESSION_COOKIE | auth_sid |
| MAIL_MAILER | smtp |
| MAIL_HOST | mail.maildog.io |
| MAIL_PORT | 2525 |
| RECAPTCHA_SITE_KEY | 6LfrdwktAAAAADqngll9t39PaELG52BcoHv8gw8v |
| GOOGLE_REDIRECT_URI | https://www.devas-projects.sbs/auth/google/callback |
| WEBAUTHN_ORIGINS | https://devas-projects.sbs,https://www.devas-projects.sbs |

### APP2 (`/var/www/app/.env`)

Misma configuracion que APP1 excepto:
- `APP_SERVER_ID=2`
- `DB_SSL_CERT=/etc/pki/mysql-client/app2.cert.pem`
- `DB_SSL_KEY=/etc/pki/mysql-client/app2.key.pem`

---

## Cloudflare

- Dominio: `devas-projects.sbs` + `www.devas-projects.sbs`
- SSL Mode: Full (Strict)
- DNS: A record → 143.110.237.225 (proxy habilitado)
- Firewall: permite puertos 80, 443 + SSH custom por servidor

---

## Middleware de Seguridad (PHP)

`app/Http/Middleware/SecurityHeadersMiddleware.php` ejecuta en cada request:

1. Elimina `X-Powered-By` y `Server`
2. Agrega: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`

CSP se maneja via `<meta>` tag en los layouts Blade (`app.blade.php` y `guest.blade.php`).

---

## SELinux (Enforcing)

- Puertos custom (52043, 53378, 53072) etiquetados con `mysqld_port_t` o tipo apropiado
- `httpd_can_network_connect_db=on` en APP1 y APP2
- `httpd_can_network_connect=on` en APP1 y APP2
- Storage/framework dirs etiquetados `httpd_sys_rw_content_t`

---

## Git Remote

- Repo: `https://github.com/SebastianRdzC04/01-practica-login.git`
- Branch: `develop`
