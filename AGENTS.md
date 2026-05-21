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

### Imágenes y rutas

- **Avatares de personajes**: siempre URLs absolutas externas (ej: `https://i.imgur.com/xxx.jpg`). Nunca rutas relativas del servidor.
- **Imágenes de diseño del foro** (banners, fondos, etc.): rutas absolutas del servidor (ej: `images/game/personaje_banner.png`) — se resuelven anteponiendo `{$mybb->settings['bburl']}`.
- En PHP, usar la función `resolve_img()` o `pj_img_url()` (según el archivo) que detecta si la ruta empieza con `http` (externa) o no (relativa al servidor) y la completa con `bburl`.
- En JS, el servidor ya devuelve URLs absolutas en los JSON; el frontend no debe resolver rutas relativas.

