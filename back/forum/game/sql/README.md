# Migraciones SQL — módulo `game/`

Scripts idempotentes ejecutados manualmente o vía [`run_pending_migrations.php`](run_pending_migrations.php) (requiere admin MyBB `cancp`).

## Orden obligatorio (núcleo)

Ejecutar en este orden en instalaciones nuevas:

1. `migrate_pj_system.php` — personajes, `game_user_config`, `game_post_characters`, contadores
2. `migrate_notifications.php`
3. `migrate_thread_meta.php`
4. `migrate_staff_levels.php`
5. `migrate_aprobar_pj.php`

## Features opcionales (según módulos activos)

| Script | Cuándo |
|--------|--------|
| `migrate_busquedas.php` | Tablón de búsquedas de rol |
| `migrate_cards.php` | Sistema de cartas |
| `migrate_cards_fields.php` | Columnas extra cartas |
| `migrate_cards_barco.php` | Cartas barco |
| `migrate_alter_dice.php` | Dados en cartas |
| `migrate_card_requests_v2.php` | Peticiones de cartas v2 |
| `migrate_character_cards_quantity.php` | Cantidad en mazo |
| `migrate_announcements.php` | Anuncios staff en index |
| `migrate_thread_pj_state.php` | PV/PE por hilo (**una vez en prod**) |
| `migrate_post_modifiers.php` | `pv_change`, `pe_change`, `modifiers_json` en posts |
| `migrate_akuma_peticiones.php` | `is_occupied`, `power_range` en Akuma + tabla `game_admin_requests` |

## Versionado

- Tabla: `mybb_game_schema_migrations` (creada por `migrate_schema_versions.php`)
- Runner: `run_pending_migrations.php` registra cada script aplicado

## Plugin `game_postcharacter`

El plugin **no** ejecuta `ALTER TABLE` en hooks. Si faltan columnas de modificadores, el guardado de PV/PE en post se omite hasta correr `migrate_post_modifiers.php`.

## Referencias

- [`docs/runbooks/backend-deploy.md`](../../../docs/runbooks/backend-deploy.md)
- `AGENTS.md` — orden núcleo documentado
