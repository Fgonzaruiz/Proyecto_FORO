## AGENTS — Guía viva del repositorio

Este archivo es **la fuente de verdad operativa** para mantener el esqueleto consistente mientras el proyecto crece.
Si una decisión no está aquí (o en `docs/`), **no existe**.

### Objetivo del sistema

- **MyBB** aporta: autenticación, sesión, permisos de foro, hilos/posts, motor de plantillas.
- El módulo **`game/`** aporta: páginas y endpoints de UX para mecánicas (fichas, inventario, tiradas, economía, staff).
- Las **mecánicas pesadas** y datos del juego viven en la **base de datos local de MyBB** (tablas prefijadas `mybb_game_*`).


### Reglas de oro

- **No lógica pesada en PHP**: PHP solo orquesta, valida “ligero” y renderiza.
- **Contratos primero**: cualquier endpoint o evento nuevo requiere:
  - contrato (OpenAPI/JSON Schema) en `packages/contracts/`
  - ejemplo de request/response en `packages/contracts/examples/`
- **Seguridad**:
  - Las operaciones de base de datos se realizan de manera interna usando el cliente de base de datos nativo de MyBB (`$db`).
- **Cierre de etiquetas PHP en plantillas**: Siempre que mezcles lógica PHP (`if`/`else`) con bloques HTML largos, asegúrate de cerrar todas las estructuras de control (`endif;`, `endforeach;`). Un `endif;` olvidado provocará un Error de Sintaxis (Parse Error) y un HTTP 500 silencioso.
- **Nombres neutros**:
  - módulo = `game/` (nunca nombres temáticos)
  - páginas = `game_*` (templates)
  - endpoints AJAX = `game/ajax/*`

### Known Issues / Technical Debt

- **Header templates unificados**: `header_notification_bell.xml` y `header_hero_date.xml` fueron fusionados en `header_unified.xml` para resolver la duplicación del template `header` de MyBB. Usar `header_unified.xml` como fuente única.
- **RPG Bridge plugin**: `back/plugin/rpg_bridge/` es esqueleto (solo README). Pendiente de implementar los webhooks hacia el backend de mecánicas.
- **Migraciones SQL**: No tienen versioning ni transacciones. Correr en orden: `migrate_pj_system.php` → `migrate_notifications.php` → `migrate_thread_meta.php` → `migrate_staff_levels.php` → `migrate_aprobar_pj.php`.

### Layout del repo (resumen)

- `opencode.json`: configuración de OpenCode (instrucciones adicionales desde `docs/`)
- `back/forum/`: runtime MyBB (docroot)
  - `game/`: módulo PHP (páginas y AJAX)
  - `jscripts/game/`: JS de game (sin framework)
- `front/`: autoría de plantillas/tema (fuentes). Ver **Sincronización fuente → XML** en la sección HTML / MyBB Templates: editar `front/templates/**`, luego `php front/update_theme.php`, `php front/validate_theme_security.php`, y commitear `front/Default-theme.xml` junto con la fuente. MyBB rechaza importación si hay `{$...}` mal formados (p. ej. `settings[\'bburl\']`) o `$\s*{` en JS.
- `packages/contracts/`: contratos (OpenAPI + JSON Schema + ejemplos)
- `docs/`: documentación de arquitectura (referenciada desde `opencode.json`)
  - `docs/arquitectura/`: overview, auth-seguridad, eventos-contratos
  - `docs/runbooks/`: `backend-deploy.md`, `frontend-deploy.md`; rotación de tokens y webhooks (solo si `rpg_bridge` se implementa)

### Entry points (convención)

- HTML: `back/forum/game/public/*.php`
  - bootstrap: `require_once __DIR__ . '/../bootstrap.php'`
  - render: template MyBB `game_*` si existe; fallback HTML mínimo
- JSON: `back/forum/game/ajax/*.php`
  - bootstrap: `require_once __DIR__ . '/../bootstrap.php'`
  - salida estándar:
    - success: `{ ok: true, data, error: null, meta }`
    - error: `{ ok: false, data: null, error: { code, message }, meta }`
  - POST mutadores: usar `Game\Http\GameAjax` (`requireLogin`, `requirePost`, `postJson`, `requireCsrf`).
  - Cliente: `jscripts/game_api.js` (`gamePostJson`) + `window.GAME_CSRF` en `headerinclude`.
- Ficha personaje: `public/personaje.php` → `personaje_init.php` → `views/personaje/page.php` (orquestador) + partials (`_tab_*.php`, `_modals.php`, `_scripts.php`).

### Checklist cuando agregas un endpoint

1. Crear entrypoint en `back/forum/game/ajax/` o `public/`.
2. Implementar UseCase en `back/forum/game/src/Application/UseCases/`.
3. Si hay consulta a base de datos: usar la clase global `$db` de MyBB de forma segura.
4. Añadir contrato OpenAPI + ejemplo de payload.
5. Añadir/actualizar template fuente en `front/templates/game/` si aplica.

### HTML / MyBB Templates

- Los templates HTML del tema están en `front/templates/mybb/` (y `front/templates/game/` para plantillas del módulo).
- **Siempre que modifiques o crees un HTML** (`front/templates/mybb/global/*.html`, etc.), debes **entregar el XML de importación** para MyBB (el código completo del template listo para pegar en el panel de administración > Templates > Edit > Advanced).
- El XML debe incluir el `template_sid` correcto y la estructura `<template>` completa si aplica; si es un template individual, basta el bloque de código del template con el nombre exacto.

#### Sincronización fuente → XML (aplica a **cualquier** template)

**Fuente de verdad:** `front/templates/**`. **Nunca** editar `front/Default-theme.xml` a mano como autoría; ese archivo es **artefacto generado** por `php front/update_theme.php`.

**Regla crítica — no sincronizar a ciegas:** `update_theme.php` **sobrescribe** cada template del XML con lo que haya en `front/templates/`. Si la fuente está desactualizada respecto al XML o al servidor, la sync **borra** contenido válido del XML y lo sustituye por una versión vieja. Esto ocurrió en jun/2026 con `index`: el tablón premium existía en `Default-theme.xml`/producción pero no en `front/templates/mybb/index/index.html`, y una sync restauró el calendario legacy (`#game-calendar-bar`) y eliminó el tablón.

**Checklist obligatorio al tocar cualquier template** (`header`, `postbit`, `forumdisplay_*`, `index`, `game_*`, etc.):

1. **Editar solo** el `.html` fuente en `front/templates/`.
2. **Antes de sincronizar**, ejecutar `php front/diff_theme_source.php` o `front/diff_theme_source.ps1` (solo lectura; **no modifica el XML**):
   - Exit **0** → fuente y XML coinciden; seguro ejecutar `update_theme.php`.
   - Exit **1** → hay diferencias o advertencias de pérdida/legacy; **no** sincronizar hasta alinear la fuente.
   - Usar `php front/diff_theme_source.php -v` para ver las primeras líneas de cada diff.
   - Si el cambio vive solo en `Default-theme.xml`, en el panel de MyBB o en producción → **copiarlo primero a la fuente** en `front/templates/`.
3. Ejecutar `php front/update_theme.php` o `python front/sync_theme_full.py` (Windows).
4. Validar con `php front/validate_theme_security.php` y confirmar de nuevo con `php front/diff_theme_source.php` (debe seguir en 0).
5. **Commitear juntos** el `.html` fuente **y** `front/Default-theme.xml` en el mismo commit.

**Checklist post-cambio frontend (CSS/JS/game):**

```text
python front/sync_theme_full.py
php front/validate_theme_security.php
python tools/audit_frontend_metrics.py   # debe exit 0
```

En prod (una vez): `game/sql/migrate_thread_pj_state.php` — ver `docs/runbooks/frontend-deploy.md`.

**Checklist post-cambio backend:**

```text
python tools/audit_backend_contracts.py   # debe exit 0
```

Ver `docs/runbooks/backend-deploy.md` y `back/forum/game/sql/README.md` (orden de migraciones).

**Prohibido / legacy (no reintroducir al sincronizar ni copiar de commits viejos):**

| Template / zona | Usar (actual) | No usar (legacy) |
|---|---|---|
| `index` — fecha on-rol | Tablón `#tablon-fecha-widget` → enlace a `calendario.php` | `#game-calendar-bar`, `#modal_calendar` (widget inline con grid de 100 días) |
| `index` — portada | `.roleplay-hero` + `.rpg-tablon-container` | Solo calendario sin tablón |
| `header` — cronología | `{$mybb->settings['game_rol_header_html']}` (plugin `game_postcharacter`) | Duplicar fecha/calendario también en `index` |
| Cualquier template | Patrones actuales del repo en `front/templates/` | Bloques eliminados del XML que no existen en fuente |

**Si un agente necesita “arreglar importación MyBB”** (comillas en `fetch`, handlers inline, etc.): corregir la **fuente** en `front/templates/` y luego sincronizar. **No** regenerar el XML desde una fuente incompleta ni asumir que el XML es la verdad.

- **Ejemplo de XML completo para importar**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<mybb>
  <templates>
    <template name="header" version="1823" sid="1">
      <![CDATA[
<div id="container">
  ...
</div>
      ]]>
    </template>
  </templates>
</mybb>
```

### Decisiones (ADR-lite)

- **D001**: Los datos y mecánicas del RPG conviven directamente en el motor local de base de datos de MyBB para evitar dependencias de red de terceros y latencias.
- **D002**: `game/` es nombre neutral del módulo; se evitan nombres temáticos.
- **D003 (Conteo de Posts)**: Los posts y temas se contabilizan por personaje, no por cuenta. Las columnas `postnum` y `threadnum` en `game_personajes` mantienen el conteo oficial del RPG. Se interceptan los hooks de MyBB (`datahandler_post_insert_post_end`, `class_moderation_delete_post`, etc.) mediante el plugin `game_postcharacter` para actualizar estas columnas.

### Imágenes y rutas

- **Avatares de personajes**: siempre URLs absolutas externas (ej: `https://i.imgur.com/xxx.jpg`). Nunca rutas relativas del servidor.
- **Imágenes de diseño del foro** (banners, fondos, etc.): rutas absolutas del servidor (ej: `images/game/personaje_banner.png`) — se resuelven anteponiendo `{$mybb->settings['bburl']}`.
- En PHP, usar la función `resolve_img()` o `pj_img_url()` (según el archivo) que detecta si la ruta empieza con `http` (externa) o no (relativa al servidor) y la completa con `bburl`.
- En JS, el servidor ya devuelve URLs absolutas en los JSON; el frontend no debe resolver rutas relativas.

### Admin / Moderación por personaje

MyBB renderiza controles de admin/mod (`{$moderationoptions}`, etc.) según el grupo del usuario MyBB. Para que solo personajes con `is_staff=1` vean estos controles:

1. **CSS**: envolver cualquier control admin/mod con `<span class="rpg-modonly">`. La clase `.rpg-modonly` está oculta por defecto (`display: none`).
2. **JS**: en `rpg_custom.js` Section 5, después de cargar el personaje activo, se agrega `body.rpg-staff` si `activeChar.is_staff` es true. CSS muestra `.rpg-modonly` solo bajo `body.rpg-staff`.
3. **Checklist**: cada vez que crees/modifiques un template que incluya variables admin/mod (`{$moderationoptions}`, `{$adminoptions}`, `{$post['button_edit']}`, `{$post['button_quickdelete']}`, etc.), envuélvelas con `class="rpg-modonly"`. Nunca asumas que todos los admins MyBB deben ver controles admin — depende del personaje activo.

### Post-character linkage (`game_post_characters`)

La tabla `game_post_characters` vincula posts y threads con el personaje activo al momento de crearlos.

**Columnas:**
- `post_id INT PRIMARY KEY` — ID del post
- `thread_id INT DEFAULT NULL` — ID del thread (solo para la primera entrada de un hilo)
- `user_id INT NOT NULL` — ID del usuario MyBB
- `character_id INT NOT NULL` — ID del personaje (`game_personajes.id`)
- `created_at TIMESTAMP`

**Flujo de captura:**
1. **Posts (respuestas)**: hook `datahandler_post_insert_post_end` → escribe con `post_id` (sin `thread_id`)
2. **Threads (hilos nuevos)**: hook `datahandler_post_insert_thread_end` → escribe con `post_id` + `thread_id`
3. El plugin `game_postcharacter.php` hace la captura automática.

**Flujo de consulta (endpoint `get_active_pj_for_user.php`):**
- `?uid=X&post_id=Y` → busca por `post_id`. Si no hay registro, devuelve `null` (no hace fallback — así los posts viejos muestran el nombre MyBB)
- `?uid=X&thread_id=Y` → busca por `thread_id`. Si no hay registro, devuelve `null`
- `?uid=X` (solo uid) → fallback al personaje activo actual del usuario

### Cronología y Red de Contactos (JSON)

Los datos de historia y relaciones de cada personaje se almacenan en la columna `cronologia_json` de `game_personajes`. La estructura esperada es:

```json
{
  "diario": [
    { "id": "...", "day": 1, "season": 0, "year": 1, "category": "Presente", "desc": "...", "link": "..." }
  ],
  "relaciones": [
    { "id": "...", "pj_id": 12, "name": "...", "tags": ["Amigo"], "desc": "...", "image": "...", "is_npc": false }
  ],
  "groups": [
    { "id": "grp_...", "name": "La Tripulación", "color": "#10b981", "members": ["id_rel_1", "id_rel_2"] }
  ]
}
```

El front-end renderiza las relaciones como un **grafo de red SVG interactivo** usando un script dedicado sin frameworks (`jscripts/game/game_network.js`). Los `groups` se representan como blobs (convex hulls) que agrupan nodos.

**Templates:**
- `postbit` (showthread): `data-uid` + `data-post-id` en `.rpg-post-pjcard` → JS pasa `post_id`
- `forumdisplay_thread` (forumdisplay): `data-uid` + `data-thread-id` en `.rpg-thread-author` → JS pasa `thread_id`
- `forumdisplay_thread` lastposter: solo `data-uid` (sin thread_id) → JS no pasa nada → endpoint devuelve personaje activo actual (fallback imperfecto para posts antiguos).

**Migración:** `migrate_pj_system.php` agrega la columna `thread_id` si no existe. Para hilos viejos sin `thread_id` en `game_post_characters`, el endpoint devuelve `null` y se mantiene el nombre MyBB.

