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

### Layout del repo (resumen)

- `back/forum/`: runtime MyBB (docroot)
  - `game/`: módulo PHP (páginas y AJAX)
  - `jscripts/game/`: JS de game (sin framework)
- `front/`: autoría de plantillas/tema (fuentes)
- `packages/contracts/`: contratos (OpenAPI + JSON Schema + ejemplos)

### Entry points (convención)

- HTML: `back/forum/game/public/*.php`
  - bootstrap: `require_once __DIR__ . '/../bootstrap.php'`
  - render: template MyBB `game_*` si existe; fallback HTML mínimo
- JSON: `back/forum/game/ajax/*.php`
  - bootstrap: `require_once __DIR__ . '/../bootstrap.php'`
  - salida estándar:
    - success: `{ ok: true, data, error: null, meta }`
    - error: `{ ok: false, data: null, error: { code, message }, meta }`

### Checklist cuando agregas un endpoint

1. Crear entrypoint en `back/forum/game/ajax/` o `public/`.
2. Implementar UseCase en `back/forum/game/src/Application/UseCases/`.
3. Si hay consulta a base de datos: usar la clase global `$db` de MyBB de forma segura.
4. Añadir contrato OpenAPI + ejemplo de payload.
5. Añadir/actualizar template fuente en `front/templates/game/` si aplica.

### HTML / MyBB Templates

- Los templates HTML del tema están en `front/templates/mybb/`.
- **Siempre que modifiques o crees un HTML** (`front/templates/mybb/global/*.html`, etc.), debes **entregar el XML de importación** para MyBB (el código completo del template listo para pegar en el panel de administración > Templates > Edit > Advanced).
- El XML debe incluir el `template_sid` correcto y la estructura `<template>` completa si aplica; si es un template individual, basta el bloque de código del template con el nombre exacto.
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

