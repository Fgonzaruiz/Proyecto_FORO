## `back/` — Runtime desplegable (docroot)

Aquí va la **instalación completa de MyBB** (lo que realmente se sube al servidor).

### Estructura propuesta

```
back/
  forum/                # docroot MyBB (NO incluido por defecto)
    admin/
    inc/
    cache/
    images/
    jscripts/
    uploads/
    game/               # módulo PHP custom opcional (páginas /game/*)
  plugin/               # fuente del plugin MyBB (para empaquetar/copiar a inc/plugins)
  sql/                  # dumps/migraciones MySQL si el juego vive en MySQL
  docker/
    docker-compose.yml  # MySQL + phpMyAdmin (dev local)
```

### Nota

- La lógica RPG vive en el módulo `game/` usando la base de datos MySQL local de MyBB.
  `back/sql/` contiene migraciones adicionales si son necesarias.

