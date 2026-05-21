## `game/` — Módulo de juego (PHP) dentro del docroot MyBB

Nombre **general** (no temático) para la capa de juego que convive dentro del runtime.

Este módulo es útil para:
- páginas custom bajo `/game/*` (ficha, inventario, panel staff, etc.)
- endpoints AJAX propios (`/game/ajax/*`) que devuelven JSON al frontend

### Bootstrap estándar MyBB (patrón)

Cada script público suele:
1. `define('IN_MYBB', 1);`
2. `require_once '../global.php';`
3. cargar dependencias desde `game/`
4. render con `$templates` o devolver JSON

### Estructura interna (Clean-ish)

```
game/
  public/                # entrypoints HTML: páginas /game/*
  ajax/                  # entrypoints JSON (sin HTML)
  staff/                 # páginas restringidas a staff
  templates/             # templates fuente (si decides versionarlas aquí; si no, usa front/)
  src/
    Domain/              # modelos + value objects + reglas puras (sin DB)
    Application/         # casos de uso (orquestación de dominio)
    Infrastructure/      # adaptadores DB/MyBB/http, repositorios concretos
    Presentation/        # view-models, mappers a templates, responders JSON
    Shared/              # helpers comunes (log, time, validation)
  sql/
    migrations/          # cambios de esquema si el juego usa MySQL
  data/                  # catálogos JSON estáticos (si aplica)
  tools/                 # scripts de mantenimiento (no producción)
```

### Nota sobre “lógica pesada”

Si tu arquitectura final manda cálculos a backend externo (Postgres/Edge Functions),
mantén aquí solo:
- validación ligera de inputs
- composición de requests/responses
- renderizado y UX

