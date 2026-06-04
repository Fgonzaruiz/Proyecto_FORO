## AGENTS — Guía operativa del repositorio

Índice breve para agentes y desarrolladores. **Reglas obligatorias numeradas (F-* frontend, B-* backend), tokens, mapas de archivos y gates CI** están en las super guías generadas por las auditorías — no duplicar aquí.

Si una decisión no está en este archivo, en `docs/` o en una regla F-*/B-* de las auditorías, **no existe** (documentar primero).

### Super guías PREMIUM (fuente de reglas)

| Ámbito | Documento | Regenerar con |
|--------|-----------|---------------|
| Frontend (plantillas, CSS, JS, diseño) | [`docs/auditoria-frontend-foro.html`](docs/auditoria-frontend-foro.html) — 35 reglas F-* | `python tools/audit_frontend_metrics.py` (exit 0) |
| Backend (PHP, SQL, plugin, AJAX, contratos) | [`docs/auditoria-backend-foro.html`](docs/auditoria-backend-foro.html) — 29 reglas B-* | `python tools/audit_backend_contracts.py` (exit 0) |

**Cuándo adjuntarlas a un agente/LLM:** cualquier cambio grande de UI/tema → frontend; cualquier cambio en `game/`, `game_postcharacter` o contratos → backend.

Catálogo editable (genera el HTML): `tools/audit_premium_catalog.py` · Runbooks: `docs/runbooks/frontend-deploy.md`, `docs/runbooks/backend-deploy.md`.

### Objetivo del sistema

- **MyBB:** autenticación, sesión, permisos, hilos/posts, plantillas.
- **`game/`:** páginas y AJAX de UX (fichas, inventario, tiradas, economía, staff).
- **Datos RPG:** MySQL local, tablas `mybb_game_*` (D001 — sin backend externo en prod).

### Reglas de oro (resumen)

Aplican siempre; detalle en auditorías **F-ORO-*** y **B-ORO-***.

| # | Regla |
|---|--------|
| 1 | PHP solo orquesta; mecánicas en MySQL vía `$db` (B-ORO-01, B-ORO-03). |
| 2 | Contratos nuevos: OpenAPI/JSON Schema en `packages/contracts/` + ejemplo (B-ORO-02). |
| 3 | Módulo neutro: `game/`, plantillas `game_*`, AJAX `game/ajax/*` (B-ORO-04). |
| 4 | Plantillas: fuente `front/templates/**`; XML es artefacto; `diff` antes de sync (F-ORO-01…05). |
| 5 | Cierre `endif;` / `endforeach;` en plantillas PHP mezcladas con HTML (B-ORO-05). |
| 6 | CSS único `back/forum/rpg_custom.css`; tokens y `.rpg-btn--*` (F-DS-01…03). |
| 7 | 0 `style=` y 0 scripts inline en templates/views; JS en `jscripts/game/` (F-GATE-01/02). |

### Layout del repo

```
back/forum/          # docroot MyBB
  game/              # módulo PHP (public/, ajax/, src/, sql/)
  jscripts/game/     # JS por feature (vanilla)
  rpg_custom.css     # tema visual (embebido en global.css vía sync)
front/
  templates/         # FUENTE plantillas (mybb/, game/)
  Default-theme.xml  # ARTEFACTO — no editar a mano
  sync_theme_full.py # sync templates + CSS → XML
packages/contracts/  # OpenAPI, schemas, examples
docs/                # arquitectura, runbooks, auditoría-*.html
inc/plugins/         # game_postcharacter.php (hooks posts/PJ)
```

### Checklists post-cambio

**Frontend** (plantillas, `rpg_custom.css`, JS de foro):

```text
php front/diff_theme_source.php          # exit 0 antes de sync
python front/sync_theme_full.py
php front/validate_theme_security.php
python tools/audit_frontend_metrics.py   # exit 0
```

**Backend** (PHP/SQL/AJAX/plugin):

```text
python tools/audit_backend_contracts.py  # exit 0
# smoke: ping, notificaciones, POST con CSRF (ver backend-deploy.md)
```

**Nuevo endpoint AJAX:** B-EP-05 — entrypoint → Service/UseCase → `$db` → OpenAPI + ejemplo → template `front/templates/game/` si aplica.

**Prod (una vez si aplica):** `game/sql/migrate_thread_pj_state.php`, `migrate_post_modifiers.php` — ver `back/forum/game/sql/README.md` y B-DAT-*.

### Decisiones (ADR-lite)

- **D001:** Datos y mecánicas en MySQL MyBB; sin APIs de red para mecánicas.
- **D002:** Nombre neutro `game/` (sin nombres temáticos en rutas).
- **D003:** Conteo de posts/hilos por personaje (`game_personajes.postnum` / `threadnum`); plugin `game_postcharacter` en hooks MyBB.

### Deuda / pendientes (no cubiertos por auditoría cerrada)

- **`back/plugin/rpg_bridge/`:** esqueleto (README); webhooks hacia mecánicas externas sin implementar.
- **Migraciones SQL:** sin versioning formal; orden núcleo en B-DAT-01 y `back/forum/game/sql/README.md`.

### Referencias

- Arquitectura: `docs/arquitectura/`
- Plantillas: `front/theme/TEMPLATES_GUIDE.md`
- Herramientas CI: `tools/README.md`
