# Practica Login - Laravel 10

Aplicacion de autenticacion y autorizacion por roles construida con Laravel 10, Breeze, Blade y MariaDB.

## Roles

- `cliente`
- `usuario`
- `administrador`
- `logger`

## Flujo de acceso

- El registro publico crea siempre usuarios con rol `cliente`.
- `cliente` entra a `/cliente`.
- `usuario`, `administrador` y `logger` entran a `/dashboard`.
- `logger` ve la tabla de auditoria de autenticacion.

## Docker

Levantar el entorno de desarrollo:

```bash
docker compose up -d --build
```

Recrear desde cero con base limpia:

```bash
docker compose down -v
docker compose up -d --build
```

## URLs

- App: `http://localhost:8080`
- Login: `http://localhost:8080/login`
- Registro: `http://localhost:8080/register`
- Vite: `http://localhost:5173`
- MariaDB desde host: `127.0.0.1:3307`

## Usuarios de prueba

- `cliente@example.com` / `Cliente123!`
- `usuario@example.com` / `Usuario123!`
- `admin@example.com` / `Admin123!`
- `logger@example.com` / `Logger123!`

## Logs

Ver logs de la app en vivo:

```bash
docker compose logs -f app
```

Ver logs de MariaDB:

```bash
docker compose logs -f mariadb
```

## Que hace el arranque del contenedor

- usa `.env.docker` dentro del contenedor
- espera a que MariaDB este disponible
- limpia cache de configuracion
- ejecuta migraciones
- ejecuta seeders
- levanta Laravel en `:8000`
- levanta Vite en `:5173`

## Verificacion realizada

Se verifico con Docker:

- `docker compose up -d --build` funcionando
- login correcto para `cliente`
- login correcto para `usuario`
- login correcto para `administrador`
- login correcto para `logger`
- registro publico creando usuario `cliente`
- persistencia de eventos `login_success` en `login_logs`
