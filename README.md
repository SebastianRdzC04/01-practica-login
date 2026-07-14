# Practica Login - Laravel 10

Aplicacion de autenticacion y autorizacion por roles construida con Laravel 10, Breeze, Blade y MySQL.

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

## Despliegue

Produccion en DigitalOcean con 4 servidores: Load Balancer (Nginx), APP1, APP2 (Laravel + PHP-FPM), MySQL.

Ver `docs/infraestructura.md` para detalles de certificados, configuraciones y topologia de red.

## Usuarios de prueba

- `cliente@example.com` / `X7eV9m.795Tnq4:5`
- `usuario@example.com` / `p.i3LNSeFjA4S.LfbMs`
- `admin@example.com` / `dbmKko%Xi4f@a^$qs^dReT2rXYk4k`
- `logger@example.com` / `Logger123!`
