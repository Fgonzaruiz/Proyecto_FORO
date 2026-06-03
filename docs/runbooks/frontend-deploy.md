# Runbook — Deploy frontend (tema + CSS + game JS)

Checklist para publicar cambios de plantillas, CSS o JS del módulo `game/` en producción.

## Pre-requisitos

- Python 3 en PATH
- PHP en PATH (para validación de tema)
- Acceso al panel admin MyBB o al XML de tema

## 1. Sincronizar fuentes → Default-theme.xml

```bash
python front/sync_theme_full.py
```

Esto actualiza:
- Templates HTML desde `front/templates/mybb/**`
- `global.css` en el XML = `mybb-minimal.css` + `back/forum/rpg_custom.css`

**Commitear** `front/Default-theme.xml` tras cambios de tema.

## 2. Validar seguridad del tema

```bash
php front/validate_theme_security.php
```

Debe exit 0. Revisa `{$...}` mal formados y `$` en JS que MyBB rechaza al importar.

## 3. Métricas de auditoría frontend

```bash
python tools/audit_frontend_metrics.py
```

Debe exit 0. Genera `docs/auditoria-metrics.json` con conteos reales.

Gates verificados:
- 0 `style=` en templates, game/public, game/views
- 0 bloques `<script>` inline (excepto `window.*_CONFIG`)
- `--accent-indigo` solo alias en `:root`
- `.rpg-form-group` definición única
- Legacy MyBB en global.css ≤ 200 líneas (stub)

## 4. Importar tema en MyBB

1. Subir / importar `front/Default-theme.xml` en Admin CP → Themes
2. O copiar archivos estáticos si el deploy es por FTP:
   - `back/forum/rpg_custom.css` (fuente; también embebido en global.css)
   - `back/forum/jscripts/game/*.js`
   - `back/forum/rpg_custom.js`

## 5. Migración BD (si aplica)

En prod, como admin CP, ejecutar una vez:

```
/game/sql/migrate_thread_pj_state.php
```

Requiere `game_require_admin_cp()`. Idempotente si la tabla/columnas ya existen.

## 6. Cache busting

- Hard refresh en navegador (Ctrl+Shift+R)
- Invalidar cache CSS del tema MyBB si el panel lo cachea
- Los scripts usan `?v=1`; incrementar query string tras cambios breaking

## Smoke test manual

| Página | Verificar |
|--------|-----------|
| Index | Tablón shimmer → contenido AJAX; hero rotatorio |
| showthread | Postbit personaje; RPG System tabs |
| newthread | Tabs sistema; editor BBCode toolbar |
| member/login | Formulario legible |
| usercp / modcp | Layout MyBB stub OK |
| game/public/personaje.php | Tabs, deck, linaje, cronología/red |
| game/public/cartas_staff.php | Drawer, catálogo |
| game/public/zona_staff_peticiones.php | Preview, chat moderación |
| game/public/crear_personaje.php | Wizard 3 pasos + preview |

## Rollback

1. Restaurar commit anterior de `Default-theme.xml` y re-importar tema
2. Restaurar `back/forum/rpg_custom.css` y JS si cambiaron
3. Re-ejecutar `sync_theme_full.py` solo si vuelves a fuentes en git
