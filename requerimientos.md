Requerimientos Aplicativo:

3 roles: guest (se crea con la interface), user (por defecto), admin (por defecto).
Validación de password (al crear la cuenta).
reCAPTCHA en todos los servicios abiertos (GUEST).
Sanitización de datos (front y back), NO REQUIRED DE HTML EN INPUTS.
Mensajes de errores claros para el usuario.
Logs de desarrollo.
Logs de auditoría (intentos de login, registro de usuarios guest), "Qué, Quién, Cuándo, Dónde".
Componentes diferentes para cada rol al iniciar la app.
Encriptación de password y token a utilizar en los factores de autentificación.
Implementación de Rate Limit en registro y login.
Commits claros y documentados.
Funciones documentadas con estándar "MENCIONE CUAL".
Factores de autentificación.
Documentación de las pruebas realizadas.
Manejo correcto de la sesión (modificación de la cabecera, no crear sesión hasta iniciar, encriptar los datos).