> **Solo aplica si se implementa `rpg_bridge` (backend externo).** En D001 (MySQL local) no hay tokens entre servicios.

## Rotación de tokens (MyBB ↔ backend mecánicas)

Checklist:
- Generar nuevo token fuerte.
- Configurar en backend (secret/env).
- Configurar en servidor MyBB (env/secret).
- Mantener ventana de solape (opcional) si el backend soporta dos tokens.
- Revocar token viejo.
- Revisar logs de auth failures tras la rotación.

