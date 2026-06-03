## Arquitectura general

> **Estado actual (D001):** Las mecánicas RPG viven en **MySQL local** del foro, orquestadas por el módulo PHP `back/forum/game/` y el plugin `game_postcharacter`. No hay backend externo en producción.

### Objetivo

Construir un foro MyBB que sirve como plataforma (auth, sesiones, hilos, posts, permisos, plantillas),
y una capa de “mecánicas RPG” que **no vive** en el core del foro pero **sí** en la misma base de datos MyBB.

### Capas (implementado hoy)

- **MyBB (runtime)**: UI, permisos base, creación de contenido, sesión de usuario.
- **Módulo `game/`**: páginas HTML (`public/`), endpoints JSON (`ajax/`), servicios en `src/Application/Services/`.
- **Plugin `game_postcharacter`**: hooks de post/hilo, vínculo personaje, contadores, fecha on-rol.
- **Contratos**: `packages/contracts/` (OpenAPI + ejemplos). Gate: `python tools/audit_backend_contracts.py`.

### Comunicación

- **Posts/hilos:** hooks MyBB → escritura en tablas `mybb_game_*`.
- **UI del juego:** `fetch` / `gamePostJson` → `game/ajax/*.php` con sesión MyBB + CSRF (`my_post_key`).
- **Envelope JSON estándar:** `{ ok, data, error: { code, message }, meta }`.

### Visión futura (no implementada)

`back/plugin/rpg_bridge/` está documentado como esqueleto para webhooks HTTP hacia un backend externo. Hasta entonces, ignorar flujos Bearer/HMAC de docs legacy; ver `auth-y-seguridad.md` actualizado.

### Deploy

- Backend: [`docs/runbooks/backend-deploy.md`](../runbooks/backend-deploy.md)
- Frontend/tema: [`docs/runbooks/frontend-deploy.md`](../runbooks/frontend-deploy.md)
