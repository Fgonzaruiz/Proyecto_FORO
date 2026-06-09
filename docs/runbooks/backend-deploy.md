# Runbook — Deploy backend (módulo `game/`)

Checklist antes y después de desplegar cambios PHP, SQL o plugin en producción.

## Pre-deploy

1. **No definir** `GAME_DEBUG` ni `GAME_ALLOW_MAINTENANCE` en producción.
2. Confirmar `display_errors=Off` en `php.ini` del servidor.
3. Revisar migraciones pendientes: ver [back/forum/game/sql/README.md](../../back/forum/game/sql/README.md).
4. Ejecutar en local/staging:
   ```bash
   python tools/audit_backend_contracts.py
   ```

## Migraciones de esquema

1. En entorno con flag temporal, definir `GAME_ALLOW_MAINTENANCE` en la config del servidor **solo durante la ventana de mantenimiento**, o ejecutar migraciones desde admin ya autenticado en dev.
2. Orden núcleo (instalación nueva):
   - `migrate_pj_system.php` → `migrate_notifications.php` → `migrate_thread_meta.php` → `migrate_staff_levels.php` → `migrate_aprobar_pj.php`
3. Features según módulos activos (busquedas, cards, announcements, etc.) — ver README en `game/sql/`.
4. **Producción (una vez):** `migrate_thread_pj_state.php` si el tablón/hilos usan PV/PE por hilo.
5. **Post modifiers:** `migrate_post_modifiers.php` antes de usar modificadores PV/PE en posts (el plugin ya no hace ALTER en runtime).

Opcional: runner orquestado `game/sql/run_pending_migrations.php` (requiere admin CP + `GAME_ALLOW_MAINTENANCE` o `GAME_DEBUG`).

## Plugin MyBB

1. Admin CP → Plugins → **Game Post Character Linker** debe estar **activo**.
2. Tras deploy, verificar que un post nuevo crea fila en `mybb_game_post_characters`.

## Post-deploy (smoke)

| Prueba | Esperado |
|--------|----------|
| `GET /game/ajax/ping.php` | `{ "ok": true, ... }` |
| Usuario logueado → `GET /game/ajax/notifications_count.php` | 200 + número |
| POST mutador sin CSRF | 403 |
| `GET /game/public/clean_db.php` (sin flags dev) | **404** |
| Tablón index + postbit | PJ activo visible en posts nuevos |

## Endurecimiento servidor (recomendado)

Bloquear en nginx/Apache (return 404) incluso si alguien olvida flags PHP:

- `/game/public/clean_db.php`
- `/game/public/install_db.php`
- `/game/public/mock_data.php`
- `/game/public/lint.php`
- `/game/create_test_cards.php`

## Rollback

1. Revertir commit PHP/plugin.
2. **No** revertir migraciones SQL automáticamente; documentar columnas/tablas añadidas y plan manual si hace falta.
3. Desactivar plugin solo si se acepta perder linkage post↔PJ en posts nuevos.

## Referencias

- `AGENTS.md` — convenciones del módulo
- `docs/auditoria-backend-foro.html` — auditoría backend
- `tools/audit_backend_contracts.py` — gate contratos OpenAPI vs ajax
