## `jscripts/game/` — JS de páginas de juego

Aquí vive la interactividad de `/game/*`:
- grids (inventario, economía)
- calculadoras y previews
- llamadas a `/game/ajax/*` (si el runtime usa AJAX PHP) o a webhooks (si aplica)

### Subestructura sugerida

```
jscripts/game/
  api/          # cliente AJAX/fetch
  components/   # componentes UI (no framework)
  pages/        # entradas por página
  state/        # estado ligero (si hace falta)
  utils/        # helpers específicos de game
```

