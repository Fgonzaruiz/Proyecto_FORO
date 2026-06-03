## Auth y seguridad

> **Estado actual (D001):** Autenticación vía **sesión MyBB**. Mutaciones AJAX exigen **CSRF** con `my_post_key` / header `X-Mybb-Post-Key` (`Game\Http\GameAjax` + `jscripts/game_api.js`).

### Implementado en producción

- Login: cookie de sesión MyBB (`global.php`).
- Endpoints `game/ajax/*` mutadores: `GameAjax::requireLogin()`, `requirePost()`, `requireCsrf()`.
- Staff sensible: `staff_level` / `is_staff` del **personaje activo**, no solo grupo MyBB.
- Scripts de mantenimiento: `game_deny_public_maintenance()` (404 en prod salvo `GAME_DEBUG` / `GAME_ALLOW_MAINTENANCE`) + `game_require_admin_cp()`.

### Principios

- No confiar en inputs del cliente; validar ownership del personaje en servidor.
- IDs numéricos con `(int)`; strings con `$db->escape_string`.
- No exponer `display_errors` en producción (`GAME_DEBUG` solo en dev).

### Visión futura (rpg_bridge — no activa)

Si se implementa backend externo:

- `Authorization: Bearer <token>` entre MyBB y el servicio de mecánicas.
- Recomendación: HMAC del cuerpo (`X-Timestamp`, `X-Signature`), ventana 3–5 minutos.
- Runbooks: `docs/runbooks/rotacion-tokens.md`, `troubleshooting-webhooks.md` (aplican solo tras implementar el bridge).
