## Entry points JSON (`/game/ajax/*`)

- `ping.php`: healthcheck mínimo (dev) para confirmar bootstrap + sesión

Convención:
- Endpoints aquí devuelven JSON (`Content-Type: application/json`).
- La lógica de aplicación vive fuera (`game/src/*`).

