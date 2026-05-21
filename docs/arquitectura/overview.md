## Arquitectura general

### Objetivo

Construir un foro MyBB que sirve como plataforma (auth, sesiones, hilos, posts, permisos, plantillas),
y una capa de “mecánicas RPG” separada que **no vive** en el core del foro.

### Capas

- **MyBB (runtime)**: UI, permisos base, creación de contenido, sesión de usuario.
- **Capa de juego**:
  - **Opción (implementada)**: MySQL local vía MyBB $db + PHP en el mismo servidor (módulo `game/`).

### Comunicación

Arquitectura orientada a eventos:
- MyBB emite eventos usando hooks oficiales (plugin).
- El plugin manda webhooks HTTP a la capa de juego (Edge Function / API).
- La capa de juego valida autenticación y responde con JSON (acciones + estado).

